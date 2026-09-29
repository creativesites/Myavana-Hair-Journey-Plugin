/**
 * Home — "Every moment, strand by strand".
 *
 * Her journey as a living 3D timeline: each photo or video floats above a
 * slowly turning braid, oldest on the left, newest on the right. Drag,
 * swipe, use the arrows or scrub the date ruler underneath; the moment in
 * focus turns to face her and its video plays. Visitors see recent public
 * Community moments instead.
 *
 * WebGL via three.js (loaded only when the section nears the screen). With
 * reduced motion or no WebGL, the same timeline renders as a CSS 3D
 * cover-flow, so every visitor gets it.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.HomeTimeline = (function() {
    'use strict';

    const THREE_VERSION = '0.169.0';
    const DAY = 86400000;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let section = null;
    let stage = null;
    let focusEl = null;
    let items = [];
    let mode = 'member';
    let startedAt = null;
    let engine = null;
    let ruler = null;
    let focus = 0;
    let target = 0;
    let lastShown = -1;
    let running = false;
    let visible = false;
    let raf = 0;
    let lastT = 0;
    let playing = false;
    let playTimer = 0;
    let sweep = null;
    let dragging = false;

    const esc = (value) => {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };
    const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
    const lerp = (a, b, t) => a + (b - a) * t;
    const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

    // ------------------------------------------------------------ Data

    function normalize(raw) {
        const time = Date.parse(raw.date);
        return Object.assign({}, raw, { time: Number.isNaN(time) ? Date.now() : time });
    }

    function fmtDate(ms, opts) {
        return new Date(ms).toLocaleDateString(undefined, opts || { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }
    function fmtTime(ms) {
        return new Date(ms).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }
    function daysBetween(a, b) {
        return Math.round((b - a) / DAY);
    }

    // ------------------------------------------------------------ Boot

    function init() {
        section = document.querySelector('[data-home-timeline]');
        if (!section || section.dataset.ready) return;
        section.dataset.ready = '1';

        let data = { items: [] };
        try { data = JSON.parse(section.querySelector('[data-strand-data]').textContent || '{}'); } catch (e) { /* keep empty */ }
        mode = data.mode || 'member';
        startedAt = data.startedAt ? Date.parse(data.startedAt) : null;
        setItems(data.items || []);

        stage = section.querySelector('[data-strand-stage]');
        focusEl = section.querySelector('[data-strand-focus]');
        ruler = Ruler(section.querySelector('[data-strand-ruler]'));
        ruler.build();

        bindControls();
        bindPointer();

        // Start the engine when the section nears the screen.
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                visible = e.isIntersecting;
                if (visible && !engine) boot();
                if (visible) start(); else stop();
            });
        }, { rootMargin: '200px 0px' });
        io.observe(section);
        document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else if (visible) start(); });

        const lastReal = items.map((i) => !i.ghost).lastIndexOf(true);
        focus = target = Math.max(0, lastReal);
        showFocus(true);
        ruler.update(focus);
    }

    function setItems(list) {
        items = list.filter((i) => i && i.date).map(normalize).sort((a, b) => a.time - b.time);
        if (mode === 'member') {
            // The end of the strand is today: an open slot for the next moment.
            items.push({ ghost: true, id: 'today', time: Math.max(Date.now(), items.length ? items[items.length - 1].time + 3600000 : Date.now()), title: items.length ? 'Add today\'s moment' : 'Add your first moment', kind: 'Today', text: '' });
        }
        section.classList.toggle('is-empty', items.filter((i) => !i.ghost).length === 0);
    }

    async function boot() {
        const canGL = !reduceMotion && supportsWebGL();
        if (canGL) {
            try {
                const base = (window.myavanaNextData && window.myavanaNextData.pluginUrl) || '/wp-content/plugins/Myavana-Hair-Journey-Plugin/';
                const THREE = await import(`${base}assets/vendor/three/three.module.min.js?ver=${THREE_VERSION}`);
                engine = Strand3D(THREE, stage, items);
            } catch (err) {
                console.warn('[Strand] 3D unavailable, using CSS timeline', err);
                engine = null;
            }
        }
        if (!engine) engine = StrandCSS(stage, items);
        section.classList.add('is-ready', engine.kind === '3d' ? 'is-3d' : 'is-css');
        stage.querySelector('.myavana-strand-loading')?.remove();

        // The first time it's seen: sweep from where it started to now.
        const real = items.filter((i) => !i.ghost).length;
        if (!reduceMotion && real > 1) {
            focus = target = 0;
            const end = items.length - 1 - (items[items.length - 1].ghost ? 1 : 0);
            sweep = { from: 0, to: end, t: 0, dur: Math.min(3.2, 1.2 + real * 0.12) };
        }
        start();
    }

    function supportsWebGL() {
        try {
            const c = document.createElement('canvas');
            return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
        } catch (e) {
            return false;
        }
    }

    // ------------------------------------------------------------ Loop

    function start() {
        if (running || !engine) return;
        running = true;
        lastT = performance.now();
        raf = requestAnimationFrame(tick);
    }

    function stop() {
        running = false;
        cancelAnimationFrame(raf);
    }

    function tick(now) {
        if (!running) return;
        const dt = Math.min(0.05, (now - lastT) / 1000);
        lastT = now;

        if (sweep) {
            sweep.t += dt;
            const p = ease(clamp(sweep.t / sweep.dur, 0, 1));
            target = lerp(sweep.from, sweep.to, p);
            if (sweep.t >= sweep.dur) { target = sweep.to; sweep = null; }
        }

        const stiffness = dragging || sweep ? 18 : 7;
        focus += (target - focus) * (1 - Math.exp(-dt * stiffness));
        if (Math.abs(target - focus) < 0.0005) focus = target;

        engine.update(focus, dt, now / 1000);
        ruler.update(focus);

        const idx = Math.round(focus);
        if (idx !== lastShown && !sweep) showFocus();
        if (!sweep && Math.abs(target - focus) < 0.02 && lastShown !== idx) showFocus();

        raf = requestAnimationFrame(tick);
    }

    function goTo(i, fromUser) {
        sweep = null;
        target = clamp(Math.round(i), 0, items.length - 1);
        if (fromUser) stopPlay();
        if (!running && engine) start();
        if (!engine) { focus = target; showFocus(); ruler.update(focus); }
    }

    // ------------------------------------------------------------ Focus panel

    function showFocus(silent) {
        const idx = clamp(Math.round(target), 0, items.length - 1);
        lastShown = Math.round(focus);
        const it = items[idx];
        if (!it || !focusEl) return;
        const prev = items[idx - 1];
        const bits = [];
        if (it.ghost) {
            focusEl.innerHTML = `
                <div class="myavana-strand-focus-card is-ghost" data-anim>
                    <p class="myavana-strand-focus-kicker"><span>Today</span>${esc(fmtDate(Date.now(), { weekday: 'long', month: 'long', day: 'numeric' }))}</p>
                    <h3>${esc(it.title)}</h3>
                    <p class="myavana-strand-focus-text">${items.length > 1 ? 'A photo or a quick clip keeps the strand growing.' : 'One photo is all it takes to start your strand.'}</p>
                    <button type="button" class="myavana-btn myavana-btn-primary myavana-btn-sm" data-strand-open="${idx}">Add a moment</button>
                </div>`;
            return;
        }
        if (mode === 'member' && startedAt) bits.push(`Day ${Math.max(1, daysBetween(startedAt, it.time) + 1)}`);
        if (prev && !prev.ghost) {
            const gap = daysBetween(prev.time, it.time);
            bits.push(gap <= 0 ? 'Same day as the last' : `${gap} day${gap === 1 ? '' : 's'} after the last`);
        }
        if (it.length) bits.push(`${it.length}" length`);
        focusEl.innerHTML = `
            <div class="myavana-strand-focus-card" data-anim>
                <p class="myavana-strand-focus-kicker"><span>${esc(it.kind || 'Moment')}</span>${esc(fmtDate(it.time, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }))} · ${esc(fmtTime(it.time))}</p>
                <h3>${esc(it.title || 'Hair update')}</h3>
                ${it.text ? `<p class="myavana-strand-focus-text">${esc(it.text)}</p>` : ''}
                <div class="myavana-strand-focus-meta">
                    ${bits.map((b) => `<span>${esc(b)}</span>`).join('')}
                    <button type="button" class="myavana-strand-open" data-strand-open="${idx}">${it.video ? 'Play video' : (mode === 'member' ? 'Open' : 'See in Community')} <span aria-hidden="true">→</span></button>
                </div>
            </div>`;
        if (silent) focusEl.querySelector('[data-anim]')?.removeAttribute('data-anim');
    }

    function open(idx) {
        const it = items[idx];
        if (!it) return;
        stopPlay();
        if (it.ghost) {
            if (MyavanaNext.SmartEntry) MyavanaNext.SmartEntry.open();
            return;
        }
        if (it.video && MyavanaNext.Media) {
            MyavanaNext.Media.openPlayer(it.video, it.image);
            return;
        }
        if (mode !== 'member') {
            window.location.hash = '#community';
            return;
        }
        Lightbox.open(it);
    }

    // ------------------------------------------------------------ Controls

    function bindControls() {
        section.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-strand]');
            if (btn) {
                const act = btn.getAttribute('data-strand');
                if (act === 'prev') goTo(Math.round(target) - 1, true);
                if (act === 'next') goTo(Math.round(target) + 1, true);
                if (act === 'play') playing ? stopPlay() : startPlay();
            }
            const openBtn = e.target.closest('[data-strand-open]');
            if (openBtn) open(parseInt(openBtn.getAttribute('data-strand-open'), 10));
        });
        stage.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { e.preventDefault(); goTo(Math.round(target) - 1, true); }
            if (e.key === 'ArrowRight') { e.preventDefault(); goTo(Math.round(target) + 1, true); }
            if (e.key === 'Home') { e.preventDefault(); goTo(0, true); }
            if (e.key === 'End') { e.preventDefault(); goTo(items.length - 1, true); }
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(Math.round(target)); }
        });
    }

    function startPlay() {
        playing = true;
        section.classList.add('is-playing');
        const btn = section.querySelector('[data-strand="play"]');
        btn?.setAttribute('aria-label', 'Pause the journey');
        if (btn) btn.querySelector('span').textContent = 'Pause';
        if (Math.round(target) >= items.length - 2) goTo(0);
        const stepOnce = () => {
            if (!playing) return;
            const next = Math.round(target) + 1;
            if (next > items.length - 1 || items[next].ghost) { stopPlay(); return; }
            target = next;
            playTimer = setTimeout(stepOnce, items[next].video ? 4200 : 2600);
        };
        playTimer = setTimeout(stepOnce, 1200);
    }

    function stopPlay() {
        if (!playing) return;
        playing = false;
        clearTimeout(playTimer);
        section.classList.remove('is-playing');
        const btn = section.querySelector('[data-strand="play"]');
        btn?.setAttribute('aria-label', 'Play the journey');
        if (btn) btn.querySelector('span').textContent = 'Play';
    }

    function bindPointer() {
        let startX = 0;
        let startY = 0;
        let startFocus = 0;
        let lastX = 0;
        let lastMove = 0;
        let velocity = 0;
        let moved = false;
        let pointerId = null;
        const pxPerItem = () => (stage.clientWidth < 640 ? 150 : 240);

        stage.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            pointerId = e.pointerId;
            startX = lastX = e.clientX;
            startY = e.clientY;
            startFocus = focus;
            lastMove = performance.now();
            velocity = 0;
            moved = false;
            dragging = true;
            sweep = null;
            stopPlay();
        });
        stage.addEventListener('pointermove', (e) => {
            if (engine && engine.hover) engine.hover(e);
            if (!dragging || e.pointerId !== pointerId) return;
            const dx = e.clientX - startX;
            if (!moved && Math.abs(dx) > 6 && Math.abs(dx) > Math.abs(e.clientY - startY)) {
                moved = true;
                stage.setPointerCapture(pointerId);
                section.classList.add('is-dragging');
            }
            if (!moved) return;
            const now = performance.now();
            velocity = (e.clientX - lastX) / Math.max(1, now - lastMove);
            lastX = e.clientX;
            lastMove = now;
            target = clamp(startFocus - dx / pxPerItem(), -0.35, items.length - 0.65);
        });
        const end = (e) => {
            if (!dragging || (e && e.pointerId !== pointerId)) return;
            dragging = false;
            section.classList.remove('is-dragging');
            if (moved) {
                const fling = -velocity * 3.2;
                goTo(Math.round(target + clamp(fling, -4, 4)), true);
            } else if (e && e.type === 'pointerup' && engine) {
                const hit = engine.pick(e);
                if (hit != null) {
                    if (hit === Math.round(focus)) open(hit); else goTo(hit, true);
                }
            }
        };
        stage.addEventListener('pointerup', end);
        stage.addEventListener('pointercancel', end);
        stage.addEventListener('pointerleave', (e) => { if (engine && engine.hover) engine.hover(null); if (!moved) dragging = false; });

        // Trackpads: sideways swipes travel; page scrolling stays page scrolling.
        let wheelTimer = 0;
        stage.addEventListener('wheel', (e) => {
            if (Math.abs(e.deltaX) <= Math.abs(e.deltaY) && !e.shiftKey) return;
            e.preventDefault();
            sweep = null;
            stopPlay();
            const d = (e.shiftKey && !e.deltaX ? e.deltaY : e.deltaX) / 180;
            target = clamp(target + d, -0.35, items.length - 0.65);
            clearTimeout(wheelTimer);
            wheelTimer = setTimeout(() => goTo(Math.round(target)), 140);
        }, { passive: false });
    }

    // ------------------------------------------------------------ Ruler

    function Ruler(root) {
        let viewport = null;
        let track = null;
        let readDate = null;
        let readTime = null;
        let readDay = null;
        let xs = [];
        let markers = [];
        let lastActive = -1;
        let lastLabel = '';
        const MIN_GAP = 38;
        const MAX_GAP = 300;

        function timeToX(t) {
            // Piecewise-linear through the moments; linear beyond the ends.
            if (!items.length) return 0;
            if (t <= items[0].time) return xs[0] - (items[0].time - t) / DAY * 6;
            for (let i = 1; i < items.length; i++) {
                if (t <= items[i].time) {
                    const span = items[i].time - items[i - 1].time || 1;
                    return lerp(xs[i - 1], xs[i], (t - items[i - 1].time) / span);
                }
            }
            return xs[xs.length - 1] + (t - items[items.length - 1].time) / DAY * 6;
        }

        function build() {
            if (!root) return;
            const gaps = [];
            for (let i = 1; i < items.length; i++) gaps.push((items[i].time - items[i - 1].time) / DAY);
            const sorted = gaps.slice().sort((a, b) => a - b);
            const median = sorted.length ? Math.max(0.5, sorted[Math.floor(sorted.length / 2)]) : 7;
            const ppd = clamp(110 / median, 1.2, 80);

            xs = [0];
            const breaks = [];
            for (let i = 1; i < items.length; i++) {
                const days = (items[i].time - items[i - 1].time) / DAY;
                let gap = days * ppd;
                if (gap > MAX_GAP) { breaks.push({ i, days }); gap = MAX_GAP; }
                xs.push(xs[i - 1] + Math.max(MIN_GAP, gap));
            }
            const width = xs[xs.length - 1];

            // Month ticks (with the year on January and at the start), and
            // day ticks wherever the scale gives them room.
            const ticks = [];
            const first = new Date(items[0].time - 20 * DAY);
            const last = items[items.length - 1].time + 20 * DAY;
            const m = new Date(first.getFullYear(), first.getMonth(), 1);
            let firstLabel = true;
            let lastLabelX = -Infinity;
            let lastYear = null;
            while (m.getTime() <= last && ticks.length < 400) {
                const x = timeToX(m.getTime());
                // Where time is compressed, months crowd: keep the tick, drop the label.
                const roomy = x - lastLabelX >= 46;
                const label = m.toLocaleDateString(undefined, { month: 'short' });
                const showYear = roomy && (firstLabel || m.getFullYear() !== lastYear);
                ticks.push(`<span class="myavana-strand-tick is-month${roomy ? '' : ' is-quiet'}" style="left:${x.toFixed(1)}px">${roomy ? `<b>${esc(label)}</b>` : ''}${showYear ? `<i>${m.getFullYear()}</i>` : ''}</span>`);
                if (roomy) { lastLabelX = x; firstLabel = false; lastYear = m.getFullYear(); }
                m.setMonth(m.getMonth() + 1);
            }
            let dayTicks = 0;
            for (let i = 1; i < items.length && dayTicks < 900; i++) {
                const days = Math.floor((items[i].time - items[i - 1].time) / DAY);
                const pxPerDay = (xs[i] - xs[i - 1]) / Math.max(1, days);
                if (pxPerDay < 7 || days < 2) continue;
                const d0 = new Date(items[i - 1].time);
                d0.setHours(0, 0, 0, 0);
                for (let k = 1; k <= days && dayTicks < 900; k++) {
                    const t = d0.getTime() + k * DAY;
                    if (t >= items[i].time) break;
                    ticks.push(`<span class="myavana-strand-tick" style="left:${timeToX(t).toFixed(1)}px"></span>`);
                    dayTicks++;
                }
            }

            const breakHtml = breaks.map((b) => {
                const x = (xs[b.i - 1] + xs[b.i]) / 2;
                const months = Math.round(b.days / 30);
                const label = months >= 2 ? `${months} months` : `${Math.round(b.days)} days`;
                return `<span class="myavana-strand-break" style="left:${x.toFixed(1)}px">${esc(label)}</span>`;
            }).join('');

            const markerHtml = items.map((it, i) => `
                <button type="button" class="myavana-strand-marker${it.ghost ? ' is-ghost' : ''}${it.video ? ' is-video' : ''}" style="left:${xs[i].toFixed(1)}px" data-mark="${i}"
                    aria-label="${esc(it.ghost ? it.title : `${it.title || it.kind}, ${fmtDate(it.time)}`)}">
                    <span class="myavana-strand-marker-dot"></span>
                    ${it.image ? `<span class="myavana-strand-marker-peek"><img src="${esc(it.image)}" alt="" loading="lazy" /></span>` : ''}
                </button>`).join('');

            root.innerHTML = `
                <div class="myavana-strand-readout" aria-hidden="true">
                    <span class="myavana-strand-readout-day"></span>
                    <strong class="myavana-strand-readout-date"></strong>
                    <span class="myavana-strand-readout-time"></span>
                </div>
                <div class="myavana-strand-viewport">
                    <div class="myavana-strand-track" style="width:${width.toFixed(0)}px">
                        <span class="myavana-strand-line"></span>
                        ${ticks.join('')}
                        ${breakHtml}
                        ${markerHtml}
                    </div>
                    <span class="myavana-strand-playhead" aria-hidden="true"></span>
                </div>`;
            viewport = root.querySelector('.myavana-strand-viewport');
            track = root.querySelector('.myavana-strand-track');
            readDate = root.querySelector('.myavana-strand-readout-date');
            readTime = root.querySelector('.myavana-strand-readout-time');
            readDay = root.querySelector('.myavana-strand-readout-day');
            markers = Array.from(root.querySelectorAll('[data-mark]'));

            root.addEventListener('click', (e) => {
                const mk = e.target.closest('[data-mark]');
                if (mk && !scrubbed) goTo(parseInt(mk.getAttribute('data-mark'), 10), true);
            });
            bindScrub();
        }

        // Drag the ruler itself to scrub through time.
        let scrubbed = false;
        function bindScrub() {
            let sx = 0;
            let sf = 0;
            let active = false;
            viewport.addEventListener('pointerdown', (e) => {
                active = true;
                scrubbed = false;
                sx = e.clientX;
                sf = focus;
                sweep = null;
                stopPlay();
            });
            viewport.addEventListener('pointermove', (e) => {
                if (!active) return;
                const dx = e.clientX - sx;
                if (!scrubbed && Math.abs(dx) < 5) return;
                if (!scrubbed) { scrubbed = true; viewport.setPointerCapture(e.pointerId); dragging = true; }
                const x = xAt(sf) - dx;
                target = focusAtX(x);
            });
            const end = () => {
                if (!active) return;
                active = false;
                if (scrubbed) {
                    dragging = false;
                    goTo(Math.round(target), true);
                    setTimeout(() => { scrubbed = false; }, 0);
                }
            };
            viewport.addEventListener('pointerup', end);
            viewport.addEventListener('pointercancel', end);
        }

        function xAt(f) {
            const i = clamp(Math.floor(f), 0, xs.length - 1);
            const j = clamp(i + 1, 0, xs.length - 1);
            if (f < 0) return xs[0] + f * MIN_GAP;
            if (f > xs.length - 1) return xs[xs.length - 1] + (f - xs.length + 1) * MIN_GAP;
            return lerp(xs[i], xs[j], f - i);
        }

        function timeAt(f) {
            const i = clamp(Math.floor(f), 0, items.length - 1);
            const j = clamp(i + 1, 0, items.length - 1);
            return lerp(items[i].time, items[j].time, clamp(f - i, 0, 1));
        }

        function focusAtX(x) {
            if (x <= xs[0]) return clamp((x - xs[0]) / MIN_GAP, -0.35, 0);
            for (let i = 1; i < xs.length; i++) {
                if (x <= xs[i]) return i - 1 + (x - xs[i - 1]) / Math.max(1, xs[i] - xs[i - 1]);
            }
            return clamp(xs.length - 1 + (x - xs[xs.length - 1]) / MIN_GAP, 0, items.length - 0.65);
        }

        function update(f) {
            if (!track) return;
            const x = xAt(f);
            track.style.transform = `translate3d(${(viewport.clientWidth / 2 - x).toFixed(1)}px,0,0)`;

            const idx = clamp(Math.round(f), 0, items.length - 1);
            if (idx !== lastActive) {
                markers[lastActive]?.classList.remove('is-active');
                markers[idx]?.classList.add('is-active');
                lastActive = idx;
            }

            // The readout rolls through the dates as you move.
            const settled = Math.abs(f - Math.round(f)) < 0.02;
            const it = items[idx];
            const t = settled ? it.time : timeAt(f);
            const label = fmtDate(t, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            if (label !== lastLabel) {
                readDate.textContent = label;
                readDate.classList.remove('is-tick');
                void readDate.offsetWidth;
                readDate.classList.add('is-tick');
                lastLabel = label;
            }
            readTime.textContent = settled ? (it.ghost ? 'Now' : fmtTime(t)) : fmtTime(t);
            readDay.textContent = mode === 'member' && startedAt
                ? (it.ghost && settled ? 'Next' : `Day ${Math.max(1, daysBetween(startedAt, t) + 1)}`)
                : 'Community';
        }

        return { build, update };
    }

    // ------------------------------------------------------------ Lightbox

    const Lightbox = (function() {
        let el = null;
        function openBox(it) {
            if (!el) {
                el = document.createElement('div');
                el.className = 'myavana-strand-lightbox';
                el.hidden = true;
                el.setAttribute('role', 'dialog');
                el.setAttribute('aria-modal', 'true');
                el.addEventListener('click', (e) => {
                    if (e.target === el || e.target.closest('[data-lb-close]')) closeBox();
                    if (e.target.closest('[data-lb-journey]')) { closeBox(); window.location.hash = '#journey'; }
                });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el && !el.hidden) closeBox(); });
                document.body.appendChild(el);
            }
            el.setAttribute('aria-label', it.title || 'Journey moment');
            el.innerHTML = `
                <figure class="myavana-strand-lightbox-card">
                    ${it.image ? `<img src="${esc(it.image)}" alt="" />` : ''}
                    <figcaption>
                        <p class="myavana-strand-focus-kicker"><span>${esc(it.kind || 'Moment')}</span>${esc(fmtDate(it.time, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }))}</p>
                        <h3>${esc(it.title || 'Hair update')}</h3>
                        ${it.text ? `<p>${esc(it.text)}</p>` : ''}
                        <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-lb-journey>See it on your timeline</button>
                    </figcaption>
                    <button type="button" class="myavana-strand-lightbox-x" data-lb-close aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </figure>`;
            el.hidden = false;
            requestAnimationFrame(() => el.classList.add('is-open'));
            el.querySelector('[data-lb-close]').focus({ preventScroll: true });
        }
        function closeBox() {
            if (!el) return;
            el.classList.remove('is-open');
            el.hidden = true;
            stage && stage.focus({ preventScroll: true });
        }
        return { open: openBox };
    })();

    // ------------------------------------------------------------ CSS engine

    /** Cover-flow in CSS 3D: for reduced motion or no WebGL. */
    function StrandCSS(root, list) {
        const wrap = document.createElement('div');
        wrap.className = 'myavana-strand-flow';
        wrap.innerHTML = list.map((it, i) => `
            <div class="myavana-strand-flow-card${it.ghost ? ' is-ghost' : ''}" data-i="${i}">
                ${it.ghost ? '<span class="myavana-strand-flow-plus">+</span>' : (it.image ? `<img src="${esc(it.image)}" alt="" loading="lazy" draggable="false" />` : `<span class="myavana-strand-flow-text">${esc(it.title || it.kind)}</span>`)}
                ${it.video ? '<span class="myavana-strand-flow-play" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/></svg></span>' : ''}
                <span class="myavana-strand-flow-date">${esc(it.ghost ? 'Today' : fmtDate(it.time, { month: 'short', day: 'numeric' }))}</span>
            </div>`).join('');
        root.appendChild(wrap);
        const cards = Array.from(wrap.children);
        return {
            kind: 'css',
            update(f) {
                const w = root.clientWidth;
                const gap = w < 640 ? 130 : 210;
                cards.forEach((c, i) => {
                    const d = i - f;
                    const ad = Math.abs(d);
                    if (ad > 6) { c.style.visibility = 'hidden'; return; }
                    c.style.visibility = '';
                    const x = d * gap;
                    const rot = clamp(-d * 38, -60, 60);
                    const z = -ad * 90;
                    const s = 1 + Math.max(0, 0.16 * (1 - ad));
                    c.style.transform = `translate(-50%, -50%) translate3d(${x.toFixed(1)}px, 0, ${z.toFixed(1)}px) rotateY(${rot.toFixed(1)}deg) scale(${s.toFixed(3)})`;
                    c.style.zIndex = String(100 - Math.round(ad * 10));
                    c.style.opacity = String(clamp(1.2 - ad * 0.22, 0, 1));
                    c.classList.toggle('is-focus', ad < 0.5);
                });
            },
            pick(e) {
                const card = e.target.closest && e.target.closest('.myavana-strand-flow-card');
                return card ? parseInt(card.getAttribute('data-i'), 10) : null;
            },
        };
    }

    // ------------------------------------------------------------ 3D engine

    function Strand3D(THREE, root, list) {
        const SP = 3.0;          // spacing between moments
        const CARD_H = 2.0;
        const BRAID_Y = -1.95;
        const BRAID_R = 0.2;
        const TWIST = 1.35;      // turns of the braid per moment
        const coral = new THREE.Color('#e7a690');
        const rose = new THREE.Color('#9b5a49');
        const blush = new THREE.Color('#fce5d7');

        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, root.clientWidth < 640 ? 1.75 : 2));
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        renderer.setClearColor(0x000000, 0);
        renderer.domElement.className = 'myavana-strand-canvas';
        root.appendChild(renderer.domElement);

        const scene = new THREE.Scene();
        scene.fog = new THREE.Fog(new THREE.Color('#fbf3ee'), 11, 30);
        const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 120);

        // ---- helpers
        function roundedRect(w, h, r) {
            const s = new THREE.Shape();
            const x = -w / 2;
            const y = -h / 2;
            s.moveTo(x + r, y);
            s.lineTo(x + w - r, y);
            s.quadraticCurveTo(x + w, y, x + w, y + r);
            s.lineTo(x + w, y + h - r);
            s.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
            s.lineTo(x + r, y + h);
            s.quadraticCurveTo(x, y + h, x, y + h - r);
            s.lineTo(x, y + r);
            s.quadraticCurveTo(x, y, x + r, y);
            const g = new THREE.ShapeGeometry(s, 10);
            const pos = g.attributes.position;
            const uv = g.attributes.uv;
            for (let i = 0; i < pos.count; i++) uv.setXY(i, (pos.getX(i) - x) / w, (pos.getY(i) - y) / h);
            uv.needsUpdate = true;
            return g;
        }

        function canvasTexture(w, h, draw) {
            const c = document.createElement('canvas');
            c.width = w;
            c.height = h;
            draw(c.getContext('2d'), w, h);
            const t = new THREE.CanvasTexture(c);
            t.colorSpace = THREE.SRGBColorSpace;
            t.anisotropy = 4;
            return t;
        }

        function wrapText(ctx, text, x, y, maxW, lineH, maxLines) {
            const words = String(text || '').split(/\s+/);
            let line = '';
            let n = 0;
            for (let i = 0; i < words.length; i++) {
                const test = line ? `${line} ${words[i]}` : words[i];
                if (ctx.measureText(test).width > maxW && line) {
                    ctx.fillText(n === maxLines - 1 ? `${line}…` : line, x, y + n * lineH);
                    n++;
                    line = words[i];
                    if (n >= maxLines) return;
                } else {
                    line = test;
                }
            }
            if (line && n < maxLines) ctx.fillText(line, x, y + n * lineH);
        }

        const font = (w, s) => `${w} ${s}px Archivo, "Helvetica Neue", Arial, sans-serif`;

        function textCard(it) {
            return canvasTexture(512, 640, (ctx, w, h) => {
                const g = ctx.createLinearGradient(0, 0, w, h);
                g.addColorStop(0, '#fdf8f5');
                g.addColorStop(1, '#fce5d7');
                ctx.fillStyle = g;
                ctx.fillRect(0, 0, w, h);
                ctx.fillStyle = '#9b5a49';
                ctx.font = font(700, 26);
                ctx.fillText(String(it.kind || 'Moment').toUpperCase(), 44, 90);
                ctx.fillStyle = '#222323';
                ctx.font = font(800, 52);
                wrapText(ctx, it.title || 'Hair update', 44, 170, w - 88, 60, 4);
                ctx.fillStyle = '#6b6b6b';
                ctx.font = font(400, 26);
                wrapText(ctx, it.text || '', 44, 440, w - 88, 36, 4);
            });
        }

        function ghostCard(it) {
            return canvasTexture(512, 640, (ctx, w, h) => {
                ctx.fillStyle = 'rgba(255,255,255,0.72)';
                ctx.fillRect(0, 0, w, h);
                ctx.setLineDash([18, 14]);
                ctx.lineWidth = 6;
                ctx.strokeStyle = '#e7a690';
                ctx.strokeRect(24, 24, w - 48, h - 48);
                ctx.setLineDash([]);
                ctx.fillStyle = '#222323';
                ctx.beginPath();
                ctx.arc(w / 2, h / 2 - 40, 58, 0, Math.PI * 2);
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 8;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(w / 2 - 24, h / 2 - 40);
                ctx.lineTo(w / 2 + 24, h / 2 - 40);
                ctx.moveTo(w / 2, h / 2 - 64);
                ctx.lineTo(w / 2, h / 2 - 16);
                ctx.stroke();
                ctx.fillStyle = '#222323';
                ctx.textAlign = 'center';
                ctx.font = font(800, 34);
                wrapText(ctx, it.title, w / 2, h / 2 + 80, w - 100, 42, 2);
            });
        }

        const loadingTex = canvasTexture(64, 80, (ctx, w, h) => {
            const g = ctx.createLinearGradient(0, 0, w, h);
            g.addColorStop(0, '#fce5d7');
            g.addColorStop(1, '#f3d2c2');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, w, h);
        });

        const dateTex = (it) => canvasTexture(256, 72, (ctx, w, h) => {
            const label = it.ghost ? 'TODAY' : fmtDate(it.time, { month: 'short', day: 'numeric' }).toUpperCase();
            ctx.font = font(700, 26);
            const tw = ctx.measureText(label).width + 36;
            const x = (w - tw) / 2;
            ctx.fillStyle = it.ghost ? '#e7a690' : '#222323';
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, 12, tw, 46, 23); else ctx.rect(x, 12, tw, 46);
            ctx.fill();
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(label, w / 2, 36);
        });

        const playTex = canvasTexture(128, 128, (ctx) => {
            ctx.fillStyle = 'rgba(255,255,255,0.94)';
            ctx.beginPath();
            ctx.arc(64, 64, 56, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#222323';
            ctx.beginPath();
            ctx.moveTo(52, 40);
            ctx.lineTo(92, 64);
            ctx.lineTo(52, 88);
            ctx.closePath();
            ctx.fill();
        });

        const shadowTex = canvasTexture(128, 128, (ctx) => {
            const g = ctx.createRadialGradient(64, 64, 4, 64, 64, 64);
            g.addColorStop(0, 'rgba(80,40,30,0.35)');
            g.addColorStop(1, 'rgba(80,40,30,0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, 128, 128);
        });

        const glowTex = canvasTexture(128, 128, (ctx) => {
            const g = ctx.createRadialGradient(64, 64, 2, 64, 64, 64);
            g.addColorStop(0, 'rgba(231,166,144,0.75)');
            g.addColorStop(1, 'rgba(231,166,144,0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, 128, 128);
        });

        // ---- the braid: two strands turning around the time axis
        const braid = new THREE.Group();
        braid.position.y = BRAID_Y;
        scene.add(braid);
        const uStart = -2.5;
        const uEnd = list.length + 1.5;
        const strands = [0, Math.PI].map((phase, k) => {
            const pts = [];
            for (let u = uStart; u <= uEnd; u += 0.08) {
                const a = u * TWIST * Math.PI + phase;
                pts.push(new THREE.Vector3(u * SP, Math.sin(a) * BRAID_R, Math.cos(a) * BRAID_R));
            }
            const curve = new THREE.CatmullRomCurve3(pts);
            const segs = Math.max(120, pts.length * 3);
            const geo = new THREE.TubeGeometry(curve, segs, k ? 0.045 : 0.055, 10, false);
            const mat = new THREE.MeshBasicMaterial({ color: k ? rose : coral, transparent: true, opacity: k ? 0.85 : 1 });
            const mesh = new THREE.Mesh(geo, mat);
            braid.add(mesh);
            return { geo, count: geo.index.count };
        });
        const core = new THREE.Mesh(
            new THREE.CylinderGeometry(0.012, 0.012, (uEnd - uStart) * SP, 6),
            new THREE.MeshBasicMaterial({ color: blush })
        );
        core.rotation.z = Math.PI / 2;
        core.position.x = ((uStart + uEnd) / 2) * SP;
        braid.add(core);

        // ---- sparkles drifting around the strand
        const P = root.clientWidth < 640 ? 160 : 320;
        const pGeo = new THREE.BufferGeometry();
        const pPos = new Float32Array(P * 3);
        const pSeed = new Float32Array(P);
        for (let i = 0; i < P; i++) {
            pPos[i * 3] = lerp(uStart, uEnd, Math.random()) * SP;
            pPos[i * 3 + 1] = lerp(-2.8, 2.4, Math.random());
            pPos[i * 3 + 2] = lerp(-3.5, 1.8, Math.random());
            pSeed[i] = Math.random() * Math.PI * 2;
        }
        pGeo.setAttribute('position', new THREE.BufferAttribute(pPos, 3));
        const dotTex = canvasTexture(32, 32, (ctx) => {
            const g = ctx.createRadialGradient(16, 16, 0, 16, 16, 16);
            g.addColorStop(0, 'rgba(255,255,255,1)');
            g.addColorStop(0.4, 'rgba(255,255,255,0.8)');
            g.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = g;
            ctx.fillRect(0, 0, 32, 32);
        });
        const sparkles = new THREE.Points(pGeo, new THREE.PointsMaterial({
            color: coral, size: 0.07, map: dotTex, transparent: true, opacity: 0.75, depthWrite: false, sizeAttenuation: true,
        }));
        scene.add(sparkles);

        // ---- the moments
        const loader = new THREE.TextureLoader();
        loader.setCrossOrigin('anonymous');
        const glow = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), new THREE.MeshBasicMaterial({ map: glowTex, transparent: true, depthWrite: false, opacity: 0 }));
        glow.renderOrder = -1;
        scene.add(glow);

        const cards = list.map((it, i) => {
            const group = new THREE.Group();
            const aspect = 0.8;
            const photoMat = new THREE.MeshBasicMaterial({ map: it.ghost ? ghostCard(it) : loadingTex, transparent: true, toneMapped: false });
            const frameMat = new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true });
            const shadowMat = new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, depthWrite: false });
            const photo = new THREE.Mesh(roundedRect(CARD_H * aspect, CARD_H, 0.14), photoMat);
            const frame = new THREE.Mesh(roundedRect(CARD_H * aspect + 0.1, CARD_H + 0.1, 0.18), frameMat);
            const shadow = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), shadowMat);
            photo.position.z = 0.012;
            shadow.position.set(0.08, -0.12, -0.06);
            shadow.scale.set(CARD_H * aspect * 1.5, CARD_H * 1.35, 1);
            photo.userData.index = i;
            frame.userData.index = i;
            group.add(shadow, frame, photo);
            if (it.ghost) frame.visible = false;

            const dateMat = new THREE.MeshBasicMaterial({ map: dateTex(it), transparent: true, depthWrite: false });
            const date = new THREE.Mesh(new THREE.PlaneGeometry(0.96, 0.27), dateMat);
            date.position.set(0, CARD_H / 2 + 0.3, 0.02);
            group.add(date);

            let play = null;
            if (it.video) {
                play = new THREE.Mesh(new THREE.PlaneGeometry(0.5, 0.5), new THREE.MeshBasicMaterial({ map: playTex, transparent: true, depthWrite: false }));
                play.position.z = 0.03;
                group.add(play);
            }

            // A thread from the card down to the braid.
            const threadGeo = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(), new THREE.Vector3()]);
            const thread = new THREE.Line(threadGeo, new THREE.LineBasicMaterial({ color: rose, transparent: true, opacity: 0.35 }));
            scene.add(thread);

            // A bead where the thread meets the braid.
            const bead = new THREE.Mesh(new THREE.SphereGeometry(0.075, 16, 12), new THREE.MeshBasicMaterial({ color: it.ghost ? coral : 0x222323 }));
            scene.add(bead);

            scene.add(group);
            return {
                it, i, group, photo, frame, shadow, date, play, thread, bead,
                aspect, loaded: !!it.ghost, loading: false, appear: 0, hover: 0,
                video: null, videoTex: null, stillTex: null,
            };
        });

        function setAspect(card, a) {
            const aspect = clamp(a, 0.62, 1.45);
            card.aspect = aspect;
            card.photo.geometry.dispose();
            card.frame.geometry.dispose();
            card.photo.geometry = roundedRect(CARD_H * aspect, CARD_H, 0.14);
            card.frame.geometry = roundedRect(CARD_H * aspect + 0.1, CARD_H + 0.1, 0.18);
            card.shadow.scale.set(CARD_H * aspect * 1.5, CARD_H * 1.35, 1);
            return aspect;
        }

        function fitTexture(tex, imgAspect, cardAspect) {
            tex.repeat.set(1, 1);
            tex.offset.set(0, 0);
            if (imgAspect > cardAspect) {
                tex.repeat.x = cardAspect / imgAspect;
                tex.offset.x = (1 - tex.repeat.x) / 2;
            } else if (imgAspect < cardAspect) {
                tex.repeat.y = imgAspect / cardAspect;
                tex.offset.y = (1 - tex.repeat.y) / 2;
            }
        }

        // Load the textures nearest to where she's looking first.
        let inflight = 0;
        function pump(f) {
            if (inflight >= 3) return;
            const next = cards
                .filter((c) => !c.loaded && !c.loading)
                .sort((a, b) => Math.abs(a.i - f) - Math.abs(b.i - f))[0];
            if (!next) return;
            next.loading = true;
            if (!next.it.image) {
                next.photo.material.map = textCard(next.it);
                next.photo.material.needsUpdate = true;
                next.loaded = true;
                next.loading = false;
                setAspect(next, 0.8);
                pump(f);
                return;
            }
            inflight++;
            loader.load(next.it.image, (tex) => {
                inflight--;
                tex.colorSpace = THREE.SRGBColorSpace;
                tex.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
                const img = tex.image;
                const ia = img && img.width ? img.width / img.height : 0.8;
                const ca = setAspect(next, ia);
                fitTexture(tex, ia, ca);
                next.stillTex = tex;
                next.photo.material.map = tex;
                next.photo.material.needsUpdate = true;
                next.loaded = true;
                next.loading = false;
                pump(f);
            }, undefined, () => {
                inflight--;
                next.photo.material.map = textCard(next.it);
                next.photo.material.needsUpdate = true;
                next.loaded = true;
                next.loading = false;
                pump(f);
            });
            pump(f);
        }

        // Only the moment in focus plays its video.
        let playingCard = null;
        function syncVideo(f) {
            const idx = Math.round(f);
            const card = Math.abs(f - idx) < 0.12 ? cards[idx] : null;
            const want = card && card.it.video ? card : null;
            if (playingCard && playingCard !== want) {
                playingCard.video.pause();
                if (playingCard.stillTex) playingCard.photo.material.map = playingCard.stillTex;
                playingCard.photo.material.needsUpdate = true;
                if (playingCard.play) playingCard.play.visible = true;
                playingCard = null;
            }
            if (want && playingCard !== want) {
                if (!want.video) {
                    const v = document.createElement('video');
                    v.src = want.it.video;
                    v.crossOrigin = 'anonymous';
                    v.muted = true;
                    v.loop = true;
                    v.playsInline = true;
                    v.preload = 'auto';
                    want.video = v;
                    want.videoTex = new THREE.VideoTexture(v);
                    want.videoTex.colorSpace = THREE.SRGBColorSpace;
                    v.addEventListener('loadedmetadata', () => {
                        const va = v.videoWidth / v.videoHeight || want.aspect;
                        fitTexture(want.videoTex, va, want.aspect);
                    });
                }
                want.video.play().then(() => {
                    if (playingCard !== want) return;
                    want.photo.material.map = want.videoTex;
                    want.photo.material.needsUpdate = true;
                    if (want.play) want.play.visible = false;
                }).catch(() => {});
                playingCard = want;
            }
        }

        // ---- layout, hover and picking
        const raycaster = new THREE.Raycaster();
        const ndc = new THREE.Vector2();
        const mouse = { x: 0, y: 0, tx: 0, ty: 0 };
        let hovered = -1;

        function setNdc(e) {
            const r = renderer.domElement.getBoundingClientRect();
            ndc.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
        }

        function hitIndex() {
            raycaster.setFromCamera(ndc, camera);
            const hits = raycaster.intersectObjects(cards.map((c) => c.photo), false);
            return hits.length ? hits[0].object.userData.index : null;
        }

        function resize() {
            const w = root.clientWidth;
            const h = root.clientHeight;
            renderer.setSize(w, h, false);
            camera.aspect = w / Math.max(1, h);
            camera.updateProjectionMatrix();
        }
        const ro = new ResizeObserver(resize);
        ro.observe(root);
        resize();

        let introT = 0;
        const bp = new THREE.Vector3();

        function update(f, dt, time) {
            introT += dt;
            pump(f);
            syncVideo(f);

            mouse.x += (mouse.tx - mouse.x) * (1 - Math.exp(-dt * 4));
            mouse.y += (mouse.ty - mouse.y) * (1 - Math.exp(-dt * 4));

            const narrow = camera.aspect < 1;
            const dist = narrow ? 9.6 : 8.3;
            const cx = f * SP;
            camera.position.set(cx + mouse.x * 0.45 + Math.sin(time * 0.35) * 0.08, 0.55 + mouse.y * 0.3 + Math.sin(time * 0.5) * 0.05, dist);
            camera.lookAt(cx, -0.25, 0);

            // The braid turns slowly; drawing itself in on first view.
            braid.rotation.x = time * 0.35;
            const draw = clamp(introT / 1.6, 0, 1);
            strands.forEach((s) => s.geo.setDrawRange(0, Math.floor((s.count * ease(draw)) / 3) * 3));

            // Sparkles drift upward and wrap.
            const arr = pGeo.attributes.position.array;
            for (let i = 0; i < P; i++) {
                arr[i * 3 + 1] += dt * (0.08 + (pSeed[i] % 1) * 0.12);
                arr[i * 3] += Math.sin(time * 0.6 + pSeed[i]) * dt * 0.05;
                if (arr[i * 3 + 1] > 2.6) arr[i * 3 + 1] = -2.8;
            }
            pGeo.attributes.position.needsUpdate = true;

            let glowCard = null;
            cards.forEach((c) => {
                const d = c.i - f;
                const ad = Math.abs(d);
                // Staggered entrance, outward from where she's looking.
                c.appear = clamp((introT - 0.25 - Math.min(ad, 8) * 0.07) / 0.7, 0, 1);
                const a = ease(c.appear);
                c.hover += ((hovered === c.i ? 1 : 0) - c.hover) * (1 - Math.exp(-dt * 10));
                const focusW = Math.max(0, 1 - ad);
                const baseX = c.i * SP;
                const wave = Math.sin(c.i * 1.3) * 0.18;
                const y = 0.3 + wave * (1 - focusW) + Math.sin(time * 0.9 + c.i) * 0.05 + c.hover * 0.12 - (1 - a) * 2.4;
                const z = -Math.min(ad, 4) * 0.55 + focusW * 0.6 + c.hover * 0.2;
                c.group.position.set(baseX, y, z);
                c.group.rotation.y = clamp(-d * 0.42, -0.95, 0.95) + mouse.x * 0.06;
                c.group.rotation.z = (1 - focusW) * Math.sin(c.i * 2.1) * 0.035;
                const s = (0.84 + focusW * 0.3 + c.hover * 0.04) * (0.6 + 0.4 * a);
                c.group.scale.setScalar(s);
                const op = a * clamp(1.25 - ad * 0.12, 0.25, 1);
                c.photo.material.opacity = op;
                c.frame.material.opacity = op;
                c.shadow.material.opacity = op * (0.5 + focusW * 0.5);
                c.date.material.opacity = op * clamp(1.1 - ad * 0.25, 0.2, 1);
                if (c.play) c.play.material.opacity = op;

                // Thread: card bottom to its bead on the braid.
                const bottomY = y - (CARD_H / 2) * s;
                const ang = baseX / SP * TWIST * Math.PI + braid.rotation.x;
                bp.set(baseX, BRAID_Y + Math.sin(ang) * BRAID_R * 0.2, Math.cos(ang) * BRAID_R * 0.2);
                const tp = c.thread.geometry.attributes.position;
                tp.setXYZ(0, baseX, bottomY, z);
                tp.setXYZ(1, bp.x, bp.y, bp.z);
                tp.needsUpdate = true;
                c.thread.material.opacity = 0.18 + focusW * 0.45;
                c.bead.position.copy(bp);
                c.bead.scale.setScalar(a * (1 + focusW * 0.9));

                if (ad < 0.5) glowCard = c;
            });

            if (glowCard) {
                glow.position.set(glowCard.group.position.x, glowCard.group.position.y, glowCard.group.position.z - 0.2);
                glow.scale.set(CARD_H * glowCard.aspect * 2.3, CARD_H * 2.1, 1);
                glow.material.opacity += ((0.9 * ease(glowCard.appear)) - glow.material.opacity) * (1 - Math.exp(-dt * 6));
            } else {
                glow.material.opacity *= 0.9;
            }

            renderer.render(scene, camera);
        }

        return {
            kind: '3d',
            update,
            pick(e) {
                setNdc(e);
                return hitIndex();
            },
            hover(e) {
                if (!e) { hovered = -1; mouse.tx = 0; mouse.ty = 0; renderer.domElement.style.cursor = ''; return; }
                const r = renderer.domElement.getBoundingClientRect();
                mouse.tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
                mouse.ty = -((e.clientY - r.top) / r.height - 0.5) * 2;
                setNdc(e);
                const idx = hitIndex();
                hovered = idx == null ? -1 : idx;
                renderer.domElement.style.cursor = idx == null ? 'grab' : 'pointer';
            },
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { init };
})();
