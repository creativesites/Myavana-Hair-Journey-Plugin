/**
 * Monthly Recap — "Your September in hair". Opened from Today (early in a
 * new month) and from each month on the Timeline.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Recap = (function() {
    'use strict';

    let root = null;
    let current = null;

    function esc(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function day(value) {
        const d = new Date(String(value || '').replace(' ', 'T') + (String(value).length === 10 ? 'T00:00:00' : ''));
        return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function ensureRoot() {
        if (root) return root;
        root = document.createElement('div');
        root.className = 'myavana-recap';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'myavana-recap-title');
        root.hidden = true;
        root.innerHTML = '<div class="myavana-recap-overlay" data-recap-close></div><div class="myavana-recap-card" id="myavana-recap-card"></div>';
        document.body.appendChild(root);
        root.addEventListener('click', (e) => {
            if (e.target.closest('[data-recap-close]')) close();
            if (e.target.closest('[data-recap-share]')) share(e.target.closest('[data-recap-share]'));
            if (e.target.closest('[data-recap-share-out]')) shareOut();
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !root.hidden) close(); });
        return root;
    }

    function render(recap) {
        const card = root.querySelector('#myavana-recap-card');
        const compare = recap.first && recap.last ? `
            <div class="myavana-recap-compare">
                <figure><img src="${esc(recap.first.url)}" alt="" /><figcaption>${esc(day(recap.first.date))}</figcaption></figure>
                <figure><img src="${esc(recap.last.url)}" alt="" /><figcaption>${esc(day(recap.last.date))}</figcaption></figure>
            </div>` : (recap.first ? `<div class="myavana-recap-single"><img src="${esc(recap.first.url)}" alt="" /></div>` : '');
        const strip = recap.photos.length > 2 ? `
            <div class="myavana-recap-strip">${recap.photos.map((p) => `<img src="${esc(p.url)}" alt="" loading="lazy" />`).join('')}</div>` : '';
        const goals = recap.goalsTouched.length ? `
            <p class="myavana-recap-goals"><span>Goals you worked on</span>${recap.goalsTouched.map((g) => `<strong>${esc(g)}</strong>`).join('')}</p>` : '';
        card.innerHTML = `
            <button type="button" class="myavana-recap-close" data-recap-close aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
            <p class="myavana-recap-eyebrow">Monthly recap · ${esc(recap.year)}</p>
            <h2 id="myavana-recap-title">Your ${esc(recap.label)} in hair</h2>
            <p class="myavana-recap-summary">${esc(recap.summary)}</p>
            ${compare}
            ${strip}
            ${goals}
            <div class="myavana-recap-actions">
                <button type="button" class="myavana-btn myavana-btn-primary" data-recap-share-out>Share</button>
                <button type="button" class="myavana-btn myavana-btn-outline" data-recap-close>Close</button>
            </div>`;
    }

    async function open(month) {
        ensureRoot();
        const card = root.querySelector('#myavana-recap-card');
        card.innerHTML = '<p class="myavana-recap-loading">Putting your month together…</p>';
        root.hidden = false;
        document.body.style.overflow = 'hidden';
        try {
            current = await MyavanaNext.API.get('journal/recap', { month });
            render(current);
        } catch (err) {
            card.innerHTML = '<p class="myavana-recap-loading">Nothing logged for that month yet.</p><div class="myavana-recap-actions"><button type="button" class="myavana-btn myavana-btn-outline" data-recap-close>Close</button></div>';
        }
    }

    async function share(btn) {
        if (!current || btn.disabled) return;
        btn.disabled = true;
        btn.textContent = 'Sharing…';
        try {
            await MyavanaNext.API.post('journal/recap/share', { month: current.month });
            btn.textContent = 'Shared';
            MyavanaNext.API.showToast(`Your ${current.label} recap is in Community.`, 'success');
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Share to Community';
            MyavanaNext.API.showToast(err.message || 'We could not share your recap. Please try again.', 'error');
        }
    }

    /** The site-wide share sheet: Community plus Instagram, WhatsApp, Facebook and more. */
    function shareOut() {
        if (!current || !MyavanaNext.Share) return;
        const month = current.month;
        MyavanaNext.Share.open({
            heading: 'Share your recap',
            title: `My ${current.label} in hair`,
            text: current.summary || '',
            image: (current.last && current.last.url) || (current.first && current.first.url) || '',
            getUrl: () => MyavanaNext.API.post('share/link', { type: 'recap', month }).then((d) => d.url),
            community: () => MyavanaNext.API.post('journal/recap/share', { month }),
        });
    }

    function close() {
        if (!root) return;
        root.hidden = true;
        document.body.style.overflow = '';
    }

    // Any element with data-recap-month opens that month's recap.
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-recap-month]');
        if (!trigger) return;
        e.preventDefault();
        open(trigger.getAttribute('data-recap-month'));
    });

    return { open, close };
})();
