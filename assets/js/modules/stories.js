/**
 * Community Stories — a photo or short video that stays for 24 hours.
 *
 * A rail of story cards at the top of the feed, a Stories tab with the
 * same cards in a grid, a full-screen viewer (tap to move, hold to pause,
 * swipe down to close) and a small composer for adding one.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Stories = (function() {
    'use strict';

    const IMAGE_MS = 5500;
    const MAX_VIDEO_SECONDS = 90;

    let groups = [];
    let loaded = false;
    let loading = null;
    let rail = null;
    let grid = null;
    let feedContent = null;

    const esc = (value) => {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };
    const toast = (msg, type) => MyavanaNext.API && MyavanaNext.API.showToast(msg, type || 'info');
    const me = () => (window.myavanaNextData && window.myavanaNextData.currentUser) || null;

    // ------------------------------------------------------------ Mount

    function init() {
        if (!window.myavanaNextData || !window.myavanaNextData.isLoggedIn) return;
        feedContent = document.querySelector('#view-community .myavana-feed-content, .myavana-community-feed .myavana-feed-content');
        if (!feedContent || feedContent.querySelector('.myavana-stories-rail')) return;

        rail = document.createElement('section');
        rail.className = 'myavana-stories-rail';
        rail.setAttribute('aria-label', 'Stories');
        rail.innerHTML = `
            <div class="myavana-stories-track" role="list">${skeleton(5)}</div>
            <button type="button" class="myavana-stories-nav is-prev" aria-label="Previous stories" hidden>‹</button>
            <button type="button" class="myavana-stories-nav is-next" aria-label="More stories" hidden>›</button>`;
        // Phones: stories come first, right under the Community header.
        const header = feedContent.closest('.myavana-community-container')?.querySelector('.myavana-community-header');
        if (header && window.matchMedia('(max-width: 768px)').matches) {
            header.after(rail);
            rail.classList.add('is-top');
        } else {
            feedContent.prepend(rail);
        }

        grid = document.createElement('section');
        grid.className = 'myavana-stories-grid-view';
        grid.hidden = true;
        grid.innerHTML = `
            <header class="myavana-stories-grid-head">
                <div>
                    <p class="myavana-stories-eyebrow">Stories</p>
                    <h2>What everyone's hair is doing today</h2>
                    <p>Photos and short clips that disappear after 24 hours.</p>
                </div>
                <button type="button" class="myavana-btn myavana-btn-primary" data-story-add>Add to your story</button>
            </header>
            <div class="myavana-stories-grid" role="list"></div>`;
        feedContent.prepend(grid);

        const track = rail.querySelector('.myavana-stories-track');
        track.addEventListener('scroll', updateNav, { passive: true });
        rail.querySelector('.is-prev').addEventListener('click', () => track.scrollBy({ left: -track.clientWidth * 0.8, behavior: 'smooth' }));
        rail.querySelector('.is-next').addEventListener('click', () => track.scrollBy({ left: track.clientWidth * 0.8, behavior: 'smooth' }));

        document.addEventListener('click', onClick);

        load();
    }

    function skeleton(n) {
        return Array.from({ length: n }, () => '<div class="myavana-story-card is-skeleton" aria-hidden="true"></div>').join('');
    }

    function load(force) {
        if (loading && !force) return loading;
        loading = MyavanaNext.API.get('community/stories').then((data) => {
            groups = (data && data.groups) || [];
            loaded = true;
            render();
        }).catch(() => {
            groups = [];
            render();
        }).finally(() => { loading = null; });
        return loading;
    }

    // ------------------------------------------------------------ Render

    function cardHtml(group, index, big) {
        const first = group.items.find((i) => !i.seen) || group.items[group.items.length - 1];
        const media = first.type === 'video' ? (first.poster || '') : first.url;
        const user = group.user;
        return `
            <button type="button" class="myavana-story-card${group.allSeen ? ' is-seen' : ''}${big ? ' is-big' : ''}" role="listitem" data-story-group="${index}"
                aria-label="${esc(group.isMine ? 'Your story' : `${user.name}'s story`)}, ${group.items.length} ${group.items.length === 1 ? 'item' : 'items'}">
                ${media ? `<img class="myavana-story-card-media" src="${esc(media)}" alt="" loading="lazy" />` : '<span class="myavana-story-card-media is-blank"></span>'}
                ${first.type === 'video' ? '<span class="myavana-story-card-video" aria-hidden="true"><svg viewBox="0 0 24 24" width="12" height="12"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/></svg></span>' : ''}
                <span class="myavana-story-card-ring"><img src="${esc(user.avatar)}" alt="" onerror="this.onerror=null;this.src='${esc(user.fallback)}'" /></span>
                <span class="myavana-story-card-name">${esc(group.isMine ? 'Your story' : (user.firstName || user.name))}</span>
                ${group.items.length > 1 ? `<span class="myavana-story-card-count">${group.items.length}</span>` : ''}
            </button>`;
    }

    function addCardHtml() {
        const u = me();
        // WordPress's grey default silhouette reads as "no photo"; use the soft gradient instead.
        const raw = (u && (u.avatar || u.avatarUrl)) || '';
        const avatar = /gravatar\.com|default|mystery|mm/i.test(raw) ? '' : raw;
        return `
            <button type="button" class="myavana-story-card is-add" role="listitem" data-story-add aria-label="Add to your story">
                <span class="myavana-story-card-media is-add-bg">${avatar ? `<img src="${esc(avatar)}" alt="" />` : ''}</span>
                <span class="myavana-story-card-plus" aria-hidden="true">+</span>
                <span class="myavana-story-card-name">Add story</span>
            </button>`;
    }

    function render() {
        if (!rail) return;
        const mine = groups.findIndex((g) => g.isMine);
        const cards = [];
        cards.push(addCardHtml());
        groups.forEach((g, i) => cards.push(cardHtml(g, i)));
        const track = rail.querySelector('.myavana-stories-track');
        track.innerHTML = cards.join('');
        rail.classList.toggle('is-empty', groups.length === 0);
        if (groups.length === 0) {
            track.insertAdjacentHTML('beforeend', `
                <div class="myavana-stories-empty">
                    <strong>No stories yet today</strong>
                    <span>Share a quick photo or clip of today's hair. It disappears in 24 hours.</span>
                </div>`);
        }
        updateNav();

        if (grid) {
            const list = grid.querySelector('.myavana-stories-grid');
            list.innerHTML = groups.length
                ? groups.map((g, i) => cardHtml(g, i, true)).join('')
                : '<p class="myavana-stories-grid-empty">No stories right now. Be the first to share today\'s hair.</p>';
        }
        return mine;
    }

    function updateNav() {
        if (!rail) return;
        const track = rail.querySelector('.myavana-stories-track');
        const canScroll = track.scrollWidth > track.clientWidth + 4;
        rail.querySelector('.is-prev').hidden = !canScroll || track.scrollLeft < 8;
        rail.querySelector('.is-next').hidden = !canScroll || track.scrollLeft + track.clientWidth >= track.scrollWidth - 8;
    }

    /** Stories tab: show the grid in place of the feed. */
    function showTab(on) {
        if (!feedContent) return;
        feedContent.classList.toggle('is-stories-tab', !!on);
        if (grid) grid.hidden = !on;
        if (rail) rail.hidden = !!on;
        if (on && !loaded) load();
        if (on && grid && window.matchMedia('(max-width: 768px)').matches) {
            requestAnimationFrame(() => grid.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        }
    }

    function onClick(e) {
        if (e.target.closest('[data-story-add]')) {
            e.preventDefault();
            AddSheet.open();
            return;
        }
        const card = e.target.closest('[data-story-group]');
        if (card && (rail.contains(card) || (grid && grid.contains(card)))) {
            e.preventDefault();
            const g = groups[parseInt(card.getAttribute('data-story-group'), 10)];
            if (g) Viewer.open(groups.indexOf(g));
        }
    }

    // ------------------------------------------------------------ Viewer

    const Viewer = (function() {
        let el = null;
        let gi = 0;
        let ii = 0;
        let timer = null;
        let started = 0;
        let elapsed = 0;
        let paused = false;
        let muted = false;
        let raf = null;
        let holdTimer = null;
        let held = false;
        let touchY = null;
        let lastFocus = null;

        function build() {
            el = document.createElement('div');
            el.className = 'myavana-story-viewer';
            el.hidden = true;
            el.setAttribute('role', 'dialog');
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('aria-label', 'Story');
            el.innerHTML = `
                <div class="myavana-story-viewer-bg" aria-hidden="true"></div>
                <button type="button" class="myavana-story-jump is-prev" data-sv="prev-group" aria-label="Previous person">‹</button>
                <div class="myavana-story-stage">
                    <div class="myavana-story-bars"></div>
                    <header class="myavana-story-head">
                        <img class="myavana-story-avatar" alt="" />
                        <div><strong class="myavana-story-name"></strong><span class="myavana-story-ago"></span></div>
                        <button type="button" class="myavana-story-icon" data-sv="mute" aria-label="Mute" hidden></button>
                        <button type="button" class="myavana-story-icon" data-sv="more" aria-label="Delete this story" hidden>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                        </button>
                        <button type="button" class="myavana-story-icon" data-sv="close" aria-label="Close">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </header>
                    <div class="myavana-story-media"></div>
                    <div class="myavana-story-tap is-left" data-sv="prev"></div>
                    <div class="myavana-story-tap is-right" data-sv="next"></div>
                    <footer class="myavana-story-foot">
                        <p class="myavana-story-caption"></p>
                        <button type="button" class="myavana-story-seen" data-sv="viewers" hidden></button>
                    </footer>
                    <div class="myavana-story-viewers" hidden></div>
                </div>
                <button type="button" class="myavana-story-jump is-next" data-sv="next-group" aria-label="Next person">›</button>`;
            document.body.appendChild(el);

            el.addEventListener('click', (e) => {
                if (held) { held = false; return; }
                const a = e.target.closest('[data-sv]');
                if (!a) {
                    if (e.target === el || e.target.classList.contains('myavana-story-viewer-bg')) close();
                    return;
                }
                const act = a.getAttribute('data-sv');
                if (act === 'close') close();
                else if (act === 'prev') step(-1);
                else if (act === 'next') step(1);
                else if (act === 'prev-group') jump(-1);
                else if (act === 'next-group') jump(1);
                else if (act === 'mute') toggleMute();
                else if (act === 'more') removeCurrent();
                else if (act === 'viewers') showViewers();
            });

            // Hold anywhere on the media to pause; swipe down to close.
            const stage = el.querySelector('.myavana-story-stage');
            stage.addEventListener('pointerdown', (e) => {
                if (e.target.closest('button')) return;
                holdTimer = setTimeout(() => { held = true; pause(); }, 220);
            });
            const release = () => {
                clearTimeout(holdTimer);
                if (paused && held) resume();
            };
            stage.addEventListener('pointerup', release);
            stage.addEventListener('pointercancel', release);
            stage.addEventListener('pointerleave', release);
            stage.addEventListener('touchstart', (e) => { touchY = e.touches[0].clientY; }, { passive: true });
            stage.addEventListener('touchend', (e) => {
                if (touchY != null && e.changedTouches[0].clientY - touchY > 90) close();
                touchY = null;
            });

            document.addEventListener('keydown', (e) => {
                if (!el || el.hidden) return;
                if (e.key === 'Escape') close();
                if (e.key === 'ArrowRight') step(1);
                if (e.key === 'ArrowLeft') step(-1);
                if (e.key === ' ') { e.preventDefault(); paused ? resume() : pause(); }
            });
            document.addEventListener('visibilitychange', () => {
                if (!el.hidden && document.hidden) pause();
            });
        }

        function open(groupIndex, itemIndex) {
            if (!el) build();
            gi = groupIndex;
            const g = groups[gi];
            if (!g) return;
            ii = typeof itemIndex === 'number' ? itemIndex : Math.max(0, g.items.findIndex((i) => !i.seen));
            if (ii < 0 || ii >= g.items.length) ii = 0;
            lastFocus = document.activeElement;
            el.hidden = false;
            requestAnimationFrame(() => el.classList.add('is-open'));
            document.documentElement.classList.add('myavana-story-open');
            show();
            el.querySelector('[data-sv="close"]').focus({ preventScroll: true });
        }

        function close() {
            if (!el || el.hidden) return;
            stop();
            const v = el.querySelector('video');
            if (v) { v.pause(); v.removeAttribute('src'); v.load(); }
            el.classList.remove('is-open');
            el.hidden = true;
            document.documentElement.classList.remove('myavana-story-open');
            render();
            if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
        }

        function show() {
            stop();
            const g = groups[gi];
            const item = g.items[ii];
            el.querySelector('.myavana-story-viewers').hidden = true;

            const avatar = el.querySelector('.myavana-story-avatar');
            avatar.onerror = () => { avatar.onerror = null; avatar.src = g.user.fallback; };
            avatar.src = g.user.avatar;
            el.querySelector('.myavana-story-name').textContent = g.isMine ? 'Your story' : g.user.name;
            el.querySelector('.myavana-story-ago').textContent = `${item.ago} ago`;
            el.querySelector('.myavana-story-caption').textContent = item.caption || '';
            el.querySelector('[data-sv="more"]').hidden = !g.isMine;
            const seenBtn = el.querySelector('[data-sv="viewers"]');
            seenBtn.hidden = !g.isMine;
            if (g.isMine) seenBtn.textContent = item.views ? `Seen by ${item.views}` : 'No views yet';
            el.querySelector('.is-prev[data-sv="prev-group"]').hidden = gi === 0;
            el.querySelector('.is-next[data-sv="next-group"]').hidden = gi >= groups.length - 1;

            el.querySelector('.myavana-story-bars').innerHTML = g.items.map((_, i) =>
                `<span class="${i < ii ? 'is-done' : ''}"><i></i></span>`).join('');

            const bg = el.querySelector('.myavana-story-viewer-bg');
            const still = item.type === 'video' ? item.poster : item.url;
            const cssUrl = still ? `url("${still.replace(/"/g, '%22')}")` : 'none';
            bg.style.backgroundImage = cssUrl;
            el.querySelector('.myavana-story-stage').style.setProperty('--story-bg', cssUrl);

            const media = el.querySelector('.myavana-story-media');
            const muteBtn = el.querySelector('[data-sv="mute"]');
            if (item.type === 'video') {
                media.innerHTML = `<video playsinline preload="auto" ${item.poster ? `poster="${esc(item.poster)}"` : ''}></video>`;
                const v = media.querySelector('video');
                v.muted = muted;
                v.src = item.url;
                v.addEventListener('ended', () => step(1));
                v.addEventListener('waiting', () => el.classList.add('is-buffering'));
                v.addEventListener('playing', () => el.classList.remove('is-buffering'));
                const p = v.play();
                if (p && p.catch) p.catch(() => { muted = true; v.muted = true; v.play().catch(() => {}); paintMute(); });
                muteBtn.hidden = false;
                paintMute();
                tickVideo(v);
            } else {
                media.innerHTML = `<img src="${esc(item.url)}" alt="${esc(item.caption || '')}" />`;
                muteBtn.hidden = true;
                elapsed = 0;
                runImage();
            }

            if (!g.isMine && !item.seen) {
                item.seen = true;
                g.allSeen = g.items.every((i) => i.seen);
                MyavanaNext.API.post(`community/stories/${item.id}/view`, {}).catch(() => {});
            }

            // Warm the next image so taps feel instant.
            const next = g.items[ii + 1] || (groups[gi + 1] && groups[gi + 1].items[0]);
            if (next) { const pre = new Image(); pre.src = next.type === 'video' ? (next.poster || '') : next.url; }
        }

        function bar() {
            return el.querySelectorAll('.myavana-story-bars span')[ii];
        }

        function runImage() {
            paused = false;
            started = performance.now() - elapsed;
            const frame = (now) => {
                if (paused) return;
                elapsed = now - started;
                const f = Math.min(1, elapsed / IMAGE_MS);
                const b = bar();
                if (b) b.firstElementChild.style.transform = `scaleX(${f})`;
                if (f >= 1) { step(1); return; }
                raf = requestAnimationFrame(frame);
            };
            raf = requestAnimationFrame(frame);
        }

        function tickVideo(v) {
            paused = false;
            const frame = () => {
                if (paused || el.hidden) return;
                const it = groups[gi] && groups[gi].items[ii];
                const d = (Number.isFinite(v.duration) && v.duration) || (it && it.duration) || 0;
                const b = bar();
                if (b && d) b.firstElementChild.style.transform = `scaleX(${Math.min(1, v.currentTime / d)})`;
                raf = requestAnimationFrame(frame);
            };
            raf = requestAnimationFrame(frame);
        }

        function stop() {
            cancelAnimationFrame(raf);
            clearTimeout(timer);
        }

        function pause() {
            if (paused) return;
            paused = true;
            stop();
            el.classList.add('is-paused');
            const v = el.querySelector('video');
            if (v) v.pause();
        }

        function resume() {
            if (!paused) return;
            el.classList.remove('is-paused');
            const v = el.querySelector('video');
            if (v) { v.play().catch(() => {}); tickVideo(v); } else runImage();
        }

        function step(delta) {
            const g = groups[gi];
            if (!g) return close();
            const next = ii + delta;
            if (next >= 0 && next < g.items.length) { ii = next; show(); return; }
            if (delta > 0) {
                if (gi < groups.length - 1) { gi += 1; ii = 0; show(); } else close();
            } else if (gi > 0) {
                gi -= 1; ii = groups[gi].items.length - 1; show();
            } else {
                ii = 0; show();
            }
        }

        function jump(delta) {
            const target = gi + delta;
            if (target < 0 || target >= groups.length) return;
            gi = target;
            ii = Math.max(0, groups[gi].items.findIndex((i) => !i.seen));
            show();
        }

        function paintMute() {
            const btn = el.querySelector('[data-sv="mute"]');
            btn.setAttribute('aria-label', muted ? 'Unmute' : 'Mute');
            btn.innerHTML = muted
                ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m23 9-6 6M17 9l6 6"/></svg>'
                : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/></svg>';
        }

        function toggleMute() {
            muted = !muted;
            const v = el.querySelector('video');
            if (v) v.muted = muted;
            paintMute();
        }

        async function removeCurrent() {
            const g = groups[gi];
            const item = g && g.items[ii];
            if (!item || !g.isMine) return;
            pause();
            if (!window.confirm('Delete this story? It will disappear for everyone.')) { resume(); return; }
            try {
                await MyavanaNext.API.delete(`community/stories/${item.id}`);
                g.items.splice(ii, 1);
                toast('Story deleted.', 'success');
                if (!g.items.length) {
                    groups.splice(gi, 1);
                    close();
                    return;
                }
                if (ii >= g.items.length) ii = g.items.length - 1;
                show();
            } catch (err) {
                toast(err.message || 'Could not delete that story.', 'error');
                resume();
            }
        }

        async function showViewers() {
            const g = groups[gi];
            const item = g && g.items[ii];
            if (!item) return;
            pause();
            const box = el.querySelector('.myavana-story-viewers');
            box.hidden = false;
            box.innerHTML = '<p class="myavana-story-viewers-head">Seen by</p><p class="myavana-story-viewers-empty">Loading…</p>';
            try {
                const data = await MyavanaNext.API.get(`community/stories/${item.id}/viewers`);
                const list = (data && data.viewers) || [];
                box.innerHTML = `
                    <p class="myavana-story-viewers-head">Seen by ${list.length}<button type="button" data-sv-close-viewers aria-label="Close">✕</button></p>
                    ${list.length ? `<ul>${list.map((u) => `<li><img src="${esc(u.avatar)}" alt="" onerror="this.onerror=null;this.src='${esc(u.fallback)}'" /><span>${esc(u.name)}</span></li>`).join('')}</ul>`
                        : '<p class="myavana-story-viewers-empty">Nobody yet. Give it a little time.</p>'}`;
                box.querySelector('[data-sv-close-viewers]')?.addEventListener('click', (e) => {
                    e.stopPropagation();
                    box.hidden = true;
                    resume();
                });
            } catch (err) {
                box.innerHTML = '<p class="myavana-story-viewers-empty">Could not load viewers.</p>';
            }
        }

        return { open, close };
    })();

    // ------------------------------------------------------------ Composer

    // ------------------------------------------------------------ Add: camera or library

    const AddSheet = (function() {
        let el = null;
        function build() {
            el = document.createElement('div');
            el.className = 'myavana-story-sheet';
            el.hidden = true;
            el.setAttribute('role', 'dialog');
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('aria-label', 'Add to your story');
            el.innerHTML = `
                <div class="myavana-story-sheet-backdrop" data-ss="close"></div>
                <div class="myavana-story-sheet-card">
                    <p class="myavana-story-sheet-title">Add to your story</p>
                    <button type="button" class="myavana-story-sheet-opt" data-ss="camera">
                        <span class="myavana-story-sheet-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3.5"/></svg></span>
                        <span><strong>Camera</strong><small>Take a photo or record a clip now</small></span>
                    </button>
                    <button type="button" class="myavana-story-sheet-opt" data-ss="library">
                        <span class="myavana-story-sheet-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></span>
                        <span><strong>Photo or video</strong><small>Choose from your library</small></span>
                    </button>
                    <button type="button" class="myavana-story-sheet-cancel" data-ss="close">Cancel</button>
                </div>`;
            document.body.appendChild(el);
            el.addEventListener('click', (e) => {
                const a = e.target.closest('[data-ss]');
                if (!a) return;
                const act = a.getAttribute('data-ss');
                close();
                if (act === 'camera') Camera.open();
                if (act === 'library') pickMedia();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el && !el.hidden) close(); });
        }
        function open() {
            if (!el) build();
            el.hidden = false;
            requestAnimationFrame(() => el.classList.add('is-open'));
            el.querySelector('[data-ss="camera"]').focus({ preventScroll: true });
        }
        function close() {
            if (!el) return;
            el.classList.remove('is-open');
            el.hidden = true;
        }
        return { open };
    })();

    /**
     * A live camera for stories: photo or video (up to 60s), front/back.
     * Falls back to the phone's own camera where live capture isn't
     * available (older browsers, insecure pages, or permission refused).
     */
    const Camera = (function() {
        const MAX_SECONDS = 60;
        let el = null;
        let stream = null;
        let facing = 'user';
        let mode = 'photo';
        let recorder = null;
        let chunks = [];
        let recStart = 0;
        let recRaf = 0;
        let nativeInput = null;

        const supported = () => !!(window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        function nativeFallback() {
            if (!nativeInput) {
                nativeInput = document.createElement('input');
                nativeInput.type = 'file';
                nativeInput.accept = 'image/*,video/*';
                nativeInput.setAttribute('capture', 'environment');
                nativeInput.hidden = true;
                document.body.appendChild(nativeInput);
                nativeInput.addEventListener('change', () => {
                    const f = nativeInput.files && nativeInput.files[0];
                    nativeInput.value = '';
                    if (f) Composer.open(f);
                });
            }
            nativeInput.click();
        }

        function build() {
            el = document.createElement('div');
            el.className = 'myavana-story-camera';
            el.hidden = true;
            el.setAttribute('role', 'dialog');
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('aria-label', 'Camera');
            el.innerHTML = `
                <video class="myavana-story-camera-feed" playsinline muted autoplay></video>
                <div class="myavana-story-camera-flash" aria-hidden="true"></div>
                <header class="myavana-story-camera-top">
                    <button type="button" class="myavana-story-camera-icon" data-cam="close" aria-label="Close camera">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                    <span class="myavana-story-camera-timer" aria-live="polite"></span>
                    <button type="button" class="myavana-story-camera-icon" data-cam="flip" aria-label="Switch camera">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/></svg>
                    </button>
                </header>
                <p class="myavana-story-camera-msg" hidden></p>
                <footer class="myavana-story-camera-bottom">
                    <div class="myavana-story-camera-modes" role="tablist" aria-label="Camera mode">
                        <button type="button" role="tab" data-cam-mode="photo" aria-selected="true" class="is-active">Photo</button>
                        <button type="button" role="tab" data-cam-mode="video" aria-selected="false">Video</button>
                    </div>
                    <div class="myavana-story-camera-row">
                        <button type="button" class="myavana-story-camera-lib" data-cam="library" aria-label="Choose from library">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                        </button>
                        <button type="button" class="myavana-story-camera-shutter" data-cam="shutter" aria-label="Take photo">
                            <svg class="myavana-story-camera-ring" viewBox="0 0 88 88" aria-hidden="true"><circle cx="44" cy="44" r="40"/></svg>
                            <span></span>
                        </button>
                        <span class="myavana-story-camera-spacer"></span>
                    </div>
                </footer>`;
            document.body.appendChild(el);
            el.addEventListener('click', (e) => {
                const m = e.target.closest('[data-cam-mode]');
                if (m) { setMode(m.getAttribute('data-cam-mode')); return; }
                const a = e.target.closest('[data-cam]');
                if (!a) return;
                const act = a.getAttribute('data-cam');
                if (act === 'close') close();
                if (act === 'flip') { facing = facing === 'user' ? 'environment' : 'user'; start(); }
                if (act === 'library') { close(); pickMedia(); }
                if (act === 'shutter') shutter();
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el && !el.hidden) close(); });
        }

        async function open() {
            if (!supported()) { nativeFallback(); return; }
            if (!el) build();
            el.hidden = false;
            requestAnimationFrame(() => el.classList.add('is-open'));
            document.documentElement.classList.add('myavana-story-open');
            setMode('photo');
            const ok = await start();
            if (!ok) {
                close();
                toast('We couldn\'t open your camera. Check the browser\'s camera permission, or choose from your library.', 'error');
                nativeFallback();
            }
        }

        async function start() {
            stopStream();
            const video = { facingMode: facing, width: { ideal: 1080 }, height: { ideal: 1920 } };
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video, audio: true });
            } catch (err) {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video, audio: false });
                } catch (err2) {
                    return false;
                }
            }
            const feed = el.querySelector('.myavana-story-camera-feed');
            feed.srcObject = stream;
            feed.classList.toggle('is-mirrored', facing === 'user');
            feed.play().catch(() => {});
            // Hide "flip" when there's only one camera.
            try {
                const cams = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
                el.querySelector('[data-cam="flip"]').hidden = cams.length < 2;
            } catch (e) { /* leave it */ }
            return true;
        }

        function stopStream() {
            if (stream) stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }

        function close() {
            if (recorder && recorder.state === 'recording') { recorder.onstop = null; recorder.stop(); }
            recorder = null;
            cancelAnimationFrame(recRaf);
            stopStream();
            if (!el) return;
            el.classList.remove('is-open', 'is-recording');
            el.hidden = true;
            el.querySelector('.myavana-story-camera-timer').textContent = '';
            document.documentElement.classList.remove('myavana-story-open');
        }

        function setMode(m) {
            if (recorder && recorder.state === 'recording') return;
            mode = m;
            el.classList.toggle('is-video', m === 'video');
            el.querySelectorAll('[data-cam-mode]').forEach((b) => {
                const on = b.getAttribute('data-cam-mode') === m;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', String(on));
            });
            el.querySelector('[data-cam="shutter"]').setAttribute('aria-label', m === 'video' ? 'Start recording' : 'Take photo');
        }

        function shutter() {
            if (!stream) return;
            if (mode === 'photo') return takePhoto();
            if (recorder && recorder.state === 'recording') recorder.stop(); else record();
        }

        function takePhoto() {
            const feed = el.querySelector('.myavana-story-camera-feed');
            const w = feed.videoWidth;
            const h = feed.videoHeight;
            if (!w || !h) return;
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(feed, 0, 0, w, h);
            el.classList.remove('is-flash');
            void el.offsetWidth;
            el.classList.add('is-flash');
            canvas.toBlob((blob) => {
                if (!blob) return;
                close();
                Composer.open(new File([blob], `story-${Date.now()}.jpg`, { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.9);
        }

        function pickMime() {
            const options = ['video/mp4;codecs=avc1', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
            return options.find((t) => window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t)) || '';
        }

        function record() {
            if (!window.MediaRecorder) {
                toast('Recording isn\'t available in this browser. Choose a clip from your library instead.', 'error');
                return;
            }
            const mime = pickMime();
            chunks = [];
            try {
                recorder = new MediaRecorder(stream, mime ? { mimeType: mime, videoBitsPerSecond: 2500000 } : undefined);
            } catch (err) {
                toast('Recording isn\'t available in this browser. Choose a clip from your library instead.', 'error');
                return;
            }
            recorder.ondataavailable = (e) => { if (e.data && e.data.size) chunks.push(e.data); };
            recorder.onstop = () => {
                cancelAnimationFrame(recRaf);
                const type = (recorder && recorder.mimeType ? recorder.mimeType : mime || 'video/webm').split(';')[0];
                const blob = new Blob(chunks, { type });
                close();
                if (blob.size < 1000) return;
                const ext = type.includes('mp4') ? 'mp4' : 'webm';
                const file = new File([blob], `story-${Date.now()}.${ext}`, { type });
                file.recordedSeconds = Math.round((performance.now() - recStart) / 1000);
                Composer.open(file);
            };
            recorder.start(250);
            recStart = performance.now();
            el.classList.add('is-recording');
            el.querySelector('[data-cam="shutter"]').setAttribute('aria-label', 'Stop recording');
            const ring = el.querySelector('.myavana-story-camera-ring circle');
            const timer = el.querySelector('.myavana-story-camera-timer');
            const tick = () => {
                const secs = (performance.now() - recStart) / 1000;
                ring.style.strokeDashoffset = String(251.3 * (1 - Math.min(1, secs / MAX_SECONDS)));
                timer.textContent = `${Math.floor(secs / 60)}:${String(Math.floor(secs % 60)).padStart(2, '0')}`;
                if (secs >= MAX_SECONDS) { recorder.stop(); return; }
                recRaf = requestAnimationFrame(tick);
            };
            recRaf = requestAnimationFrame(tick);
        }

        return { open };
    })();

    let picker = null;

    function pickMedia() {
        if (!picker) {
            picker = document.createElement('input');
            picker.type = 'file';
            picker.accept = 'image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/webm,.mov,.mp4,.m4v,.webm';
            picker.hidden = true;
            document.body.appendChild(picker);
            picker.addEventListener('change', () => {
                const file = picker.files && picker.files[0];
                picker.value = '';
                if (file) Composer.open(file);
            });
        }
        picker.click();
    }

    const Composer = (function() {
        let el = null;
        let file = null;
        let objectUrl = '';
        let duration = 0;
        let posterBlob = null;
        let busy = false;

        function build() {
            el = document.createElement('div');
            el.className = 'myavana-story-composer';
            el.hidden = true;
            el.setAttribute('role', 'dialog');
            el.setAttribute('aria-modal', 'true');
            el.setAttribute('aria-labelledby', 'myavana-story-composer-title');
            el.innerHTML = `
                <div class="myavana-story-composer-backdrop" data-sc="close"></div>
                <div class="myavana-story-composer-card">
                    <header>
                        <h2 id="myavana-story-composer-title">New story</h2>
                        <button type="button" class="myavana-story-composer-x" data-sc="close" aria-label="Close">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </header>
                    <div class="myavana-story-composer-preview"></div>
                    <label class="myavana-story-composer-field">
                        <span class="screen-reader-text">Caption</span>
                        <input type="text" maxlength="280" placeholder="Add a caption (optional)" />
                    </label>
                    <p class="myavana-story-composer-note">Visible to the Community for 24 hours.</p>
                    <div class="myavana-story-composer-actions">
                        <button type="button" class="myavana-btn myavana-btn-outline" data-sc="swap">Choose another</button>
                        <button type="button" class="myavana-btn myavana-btn-primary" data-sc="share">Share to story</button>
                    </div>
                </div>`;
            document.body.appendChild(el);
            el.addEventListener('click', (e) => {
                const a = e.target.closest('[data-sc]');
                if (!a || busy) return;
                const act = a.getAttribute('data-sc');
                if (act === 'close') close();
                if (act === 'swap') { close(); AddSheet.open(); }
                if (act === 'share') share(a);
            });
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el && !el.hidden && !busy) close(); });
        }

        async function open(f) {
            const Media = MyavanaNext.Media;
            const isVideo = Media.isVideoFile(f);
            if (isVideo) {
                if (!Media.isSupportedVideo(f)) { toast(`"${f.name}" isn't a video we can play. Please use MP4 or MOV.`, 'error'); return; }
                if (f.size > Media.maxVideoBytes()) { toast(`That video is over ${Media.formatBytes(Media.maxVideoBytes())}. Try a shorter clip.`, 'error'); return; }
            } else if (!f.type.startsWith('image/')) {
                toast(`"${f.name}" isn't a photo or video.`, 'error');
                return;
            } else if (f.size > 15 * 1024 * 1024) {
                toast('That photo is over 15MB.', 'error');
                return;
            }
            if (!el) build();
            file = f;
            duration = 0;
            posterBlob = null;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = URL.createObjectURL(f);
            const preview = el.querySelector('.myavana-story-composer-preview');
            preview.innerHTML = isVideo
                ? `<video src="${esc(objectUrl)}" playsinline muted loop autoplay></video>`
                : `<img src="${esc(objectUrl)}" alt="" />`;
            el.querySelector('input').value = '';
            el.hidden = false;
            requestAnimationFrame(() => el.classList.add('is-open'));
            document.documentElement.classList.add('myavana-story-open');

            if (isVideo) {
                const still = await Media.posterFromFile(f);
                duration = (Number.isFinite(still.duration) && still.duration) || f.recordedSeconds || 0;
                posterBlob = still.blob;
                if (duration > MAX_VIDEO_SECONDS) {
                    toast(`Stories can be up to ${MAX_VIDEO_SECONDS} seconds. Longer clips fit better as a journey entry.`, 'error');
                    close();
                }
            }
        }

        function close() {
            if (!el) return;
            el.classList.remove('is-open');
            el.hidden = true;
            el.querySelector('.myavana-story-composer-preview').innerHTML = '';
            document.documentElement.classList.remove('myavana-story-open');
            if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = ''; }
        }

        async function share(btn) {
            if (!file) return;
            const Media = MyavanaNext.Media;
            const isVideo = Media.isVideoFile(file);
            busy = true;
            btn.disabled = true;
            btn.textContent = 'Sharing…';
            try {
                let url = '';
                let poster = '';
                if (isVideo) {
                    if (posterBlob) {
                        const p = await MyavanaNext.API.upload(new File([posterBlob], 'story-poster.jpg', { type: 'image/jpeg' })).catch(() => null);
                        poster = (p && p.url) || '';
                    }
                    const up = await Media.uploadWithProgress(file, (f) => { btn.textContent = `Uploading ${Math.round(f * 100)}%`; });
                    url = up.url;
                } else {
                    const up = await MyavanaNext.API.upload(file);
                    url = up.url;
                }
                await MyavanaNext.API.post('community/stories', {
                    type: isVideo ? 'video' : 'image',
                    url,
                    poster,
                    duration,
                    caption: el.querySelector('input').value.trim(),
                });
                busy = false;
                close();
                toast('Your story is live for 24 hours.', 'success');
                await load(true);
                const mine = groups.findIndex((g) => g.isMine);
                if (mine > -1) Viewer.open(mine, groups[mine].items.length - 1);
            } catch (err) {
                toast(err.message || 'We could not share your story. Please try again.', 'error');
            } finally {
                busy = false;
                btn.disabled = false;
                btn.textContent = 'Share to story';
            }
        }

        return { open, close };
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { init, load, showTab, open: (i) => Viewer.open(i), pickMedia };
})();
