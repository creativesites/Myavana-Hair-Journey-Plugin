/**
 * One share sheet for the whole site.
 *
 *   MyavanaNext.Share.open({
 *       title, text, image, video,         // what is being shared
 *       url | getUrl: () => Promise<url>,  // its public page (has preview tags)
 *       editableCaption: true,             // let her edit the words first
 *       community: () => Promise,          // optional "Post to MYAVANA Community"
 *       onShare: (platform) => {},         // analytics hook
 *   });
 *
 * Facebook, WhatsApp, X, LinkedIn, Pinterest, Threads, Messenger, email and
 * text use each network's own share address. Instagram has no web share
 * address, so on phones the photo or video itself goes to the system share
 * sheet (where Instagram is), and on computers the photo is saved and the
 * caption copied, ready to post.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Share = (function() {
    'use strict';

    const isMobile = () => /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && /Macintosh/.test(navigator.userAgent));
    const toast = (m, t) => MyavanaNext.API && MyavanaNext.API.showToast(m, t || 'success');
    const esc = (v) => {
        const d = document.createElement('div');
        d.textContent = v == null ? '' : String(v);
        return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    };
    const enc = encodeURIComponent;

    function brand(name) {
        const d = (MyavanaNext.BrandIcons || {})[name];
        return d ? `<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="${d}"/></svg>` : '';
    }
    const ICON = {
        email: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-10 6L2 7"/></svg>',
        sms: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>',
        copy: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>',
        more: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>',
        download: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg>',
        community: '<span class="myavana-share-m" aria-hidden="true">M</span>',
    };

    let el = null;
    let ctx = null;
    let urlPromise = null;
    let resolvedUrl = '';
    let lastFocus = null;

    function targets() {
        const list = [];
        if (ctx.community) list.push({ key: 'community', label: 'Community', icon: ICON.community });
        list.push({ key: 'instagram', label: 'Instagram', icon: brand('instagram') });
        list.push({ key: 'whatsapp', label: 'WhatsApp', icon: brand('whatsapp') });
        list.push({ key: 'facebook', label: 'Facebook', icon: brand('facebook') });
        if (isMobile()) list.push({ key: 'messenger', label: 'Messenger', icon: brand('messenger') });
        list.push({ key: 'x', label: 'X', icon: brand('x') });
        list.push({ key: 'threads', label: 'Threads', icon: brand('threads') });
        if (ctx.image) list.push({ key: 'pinterest', label: 'Pinterest', icon: brand('pinterest') });
        list.push({ key: 'linkedin', label: 'LinkedIn', icon: brand('linkedin') });
        if (isMobile()) list.push({ key: 'sms', label: 'Messages', icon: ICON.sms });
        list.push({ key: 'email', label: 'Email', icon: ICON.email });
        if (ctx.image && !isMobile()) list.push({ key: 'download', label: 'Save photo', icon: ICON.download });
        list.push({ key: 'copy', label: 'Copy link', icon: ICON.copy });
        if (navigator.share) list.push({ key: 'more', label: 'More', icon: ICON.more });
        return list;
    }

    function build() {
        el = document.createElement('div');
        el.className = 'myavana-share-sheet';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-labelledby', 'myavana-share-sheet-title');
        document.body.appendChild(el);
        el.addEventListener('click', (e) => {
            if (e.target.closest('[data-share-close]') || e.target === el) { close(); return; }
            const t = e.target.closest('[data-share-to]');
            if (t && !t.disabled) go(t.getAttribute('data-share-to'), t);
        });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && el && !el.hidden) close(); });
    }

    function render() {
        el.innerHTML = `
            <div class="myavana-share-sheet-card">
                <header class="myavana-share-sheet-head">
                    <h2 id="myavana-share-sheet-title">${esc(ctx.heading || 'Share')}</h2>
                    <button type="button" class="myavana-share-sheet-x" data-share-close aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="myavana-share-sheet-preview">
                    ${ctx.image ? `<img src="${esc(ctx.image)}" alt="" />` : '<span class="myavana-share-sheet-noimg" aria-hidden="true">M</span>'}
                    <div>
                        <strong>${esc(ctx.title || 'My hair journey')}</strong>
                        ${ctx.editableCaption
                            ? `<label class="myavana-share-sheet-caption"><span class="screen-reader-text">Caption</span><textarea rows="2" maxlength="600" placeholder="Add a caption">${esc(ctx.text || '')}</textarea></label>`
                            : (ctx.text ? `<p>${esc(String(ctx.text).slice(0, 140))}${String(ctx.text).length > 140 ? '…' : ''}</p>` : '')}
                    </div>
                </div>
                <div class="myavana-share-sheet-grid" role="list">
                    ${targets().map((t) => `
                        <button type="button" class="myavana-share-target is-${t.key}" data-share-to="${t.key}" role="listitem">
                            <span class="myavana-share-target-icon">${t.icon}</span>
                            <span class="myavana-share-target-label">${esc(t.label)}</span>
                        </button>`).join('')}
                </div>
                <div class="myavana-share-sheet-link">
                    <span class="myavana-share-sheet-url" aria-live="polite">Preparing your link…</span>
                </div>
            </div>`;
    }

    function caption() {
        const ta = el && el.querySelector('.myavana-share-sheet-caption textarea');
        return (ta ? ta.value : ctx.text || '').trim();
    }

    function message() {
        const c = caption();
        const base = c || ctx.title || 'My hair journey on MYAVANA';
        return base.length > 220 ? `${base.slice(0, 217)}…` : base;
    }

    let savedCaption = null;

    /** The public link (stable for a given item; fetched once per sheet). */
    async function url() {
        if (resolvedUrl) return resolvedUrl;
        if (!urlPromise) urlPromise = ctx.url ? Promise.resolve(ctx.url) : ctx.getUrl(caption());
        resolvedUrl = await urlPromise;
        savedCaption = caption();
        return resolvedUrl;
    }

    /** Entry links carry her caption into the preview; save it if she edited it. */
    function persistCaption() {
        if (!ctx.editableCaption || !ctx.getUrl) return;
        const c = caption();
        if (c === savedCaption) return;
        savedCaption = c;
        ctx.getUrl(c).catch(() => {});
    }

    function open(options) {
        if (!el) build();
        ctx = Object.assign({ heading: 'Share' }, options || {});
        urlPromise = null;
        resolvedUrl = '';
        savedCaption = null;
        lastFocus = document.activeElement;
        render();
        el.hidden = false;
        requestAnimationFrame(() => el.classList.add('is-open'));
        document.documentElement.classList.add('myavana-share-open');
        el.querySelector('[data-share-close]').focus({ preventScroll: true });

        const label = el.querySelector('.myavana-share-sheet-url');
        url().then((u) => {
            label.textContent = u.replace(/^https?:\/\//, '');
        }).catch((err) => {
            label.textContent = (err && err.message) || 'We couldn\'t create a link for this. Please try again.';
            el.querySelectorAll('[data-share-to]:not([data-share-to="community"]):not([data-share-to="instagram"]):not([data-share-to="download"])')
                .forEach((b) => { b.disabled = true; });
        });
    }

    function close() {
        if (!el || el.hidden) return;
        el.classList.remove('is-open');
        el.hidden = true;
        document.documentElement.classList.remove('myavana-share-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
    }

    function track(key) {
        try { if (ctx.onShare) ctx.onShare(key); } catch (e) { /* ignore */ }
    }

    /**
     * Popups must open inside the tap, before any await, or browsers block
     * them. The link is fetched when the sheet opens, so it's usually ready;
     * if not, open a blank tab now and point it once the link arrives.
     */
    async function openWindow(build) {
        if (resolvedUrl) {
            persistCaption();
            // No 'noopener' feature here: with it, window.open always returns
            // null and a working popup would look blocked.
            const w = window.open(build(resolvedUrl), '_blank');
            if (w) { try { w.opener = null; } catch (e) { /* cross-origin already */ } } else { window.location.href = build(resolvedUrl); }
            return;
        }
        const w = window.open('', '_blank');
        try {
            const u = await url();
            if (w && !w.closed) { w.opener = null; w.location.href = build(u); } else { window.location.href = build(u); }
        } catch (err) {
            if (w) w.close();
            toast((err && err.message) || 'We couldn\'t create a link for this.', 'error');
        }
    }

    async function copy(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (e) {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand && document.execCommand('copy');
            ta.remove();
            return !!ok;
        }
    }

    async function mediaFile() {
        const src = ctx.video || ctx.image;
        if (!src) return null;
        const res = await fetch(src, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('fetch failed');
        const blob = await res.blob();
        if (ctx.video && blob.size > 80 * 1024 * 1024) return null;
        const type = blob.type || (ctx.video ? 'video/mp4' : 'image/jpeg');
        const ext = type.includes('png') ? 'png' : type.includes('webp') ? 'webp' : type.includes('webm') ? 'webm' : type.includes('quicktime') ? 'mov' : ctx.video ? 'mp4' : 'jpg';
        return new File([blob], `myavana-hair-journey.${ext}`, { type });
    }

    async function download() {
        try {
            const f = await mediaFile();
            if (!f) throw new Error('no media');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(f);
            a.download = f.name;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
            return true;
        } catch (e) {
            if (ctx.image) window.open(ctx.image, '_blank', 'noopener');
            return false;
        }
    }

    async function go(key, btn) {
        const text = message();
        if (key === 'community') {
            btn.disabled = true;
            btn.classList.add('is-busy');
            try {
                const res = await ctx.community(caption());
                toast(res && res.alreadyShared ? 'Already in the Community.' : 'Posted to the Community.', 'success');
                track('community');
                btn.classList.add('is-done');
            } catch (err) {
                btn.disabled = false;
                toast((err && err.message) || 'We couldn\'t post that. Please try again.', 'error');
            } finally {
                btn.classList.remove('is-busy');
            }
            return;
        }

        if (key === 'instagram') {
            // Copy the words first, while the tap still counts as a user action.
            const link = resolvedUrl ? ` ${resolvedUrl}` : '';
            const copied = await copy(`${caption() || ctx.title || ''}${link}`.trim());
            if (isMobile() && navigator.canShare) {
                try {
                    const file = await mediaFile();
                    if (file && navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file] });
                        track('instagram');
                        if (copied) toast('Caption copied. Paste it into your post.', 'success');
                        return;
                    }
                } catch (err) {
                    if (err && err.name === 'AbortError') return;
                }
            }
            if (ctx.image || ctx.video) {
                await download();
                toast(copied ? 'Photo saved and caption copied. Open Instagram to post it.' : 'Photo saved. Open Instagram to post it.', 'success');
            } else {
                toast(copied ? 'Caption and link copied. Paste them into Instagram.' : 'Open Instagram to share this.', 'success');
            }
            track('instagram');
            return;
        }

        if (key === 'download') {
            await download();
            track('download');
            return;
        }

        if (key === 'copy') {
            try {
                const u = await url();
                persistCaption();
                if (await copy(u)) {
                    toast('Link copied.', 'success');
                    btn.querySelector('.myavana-share-target-label').textContent = 'Copied';
                    setTimeout(() => { if (btn.isConnected) btn.querySelector('.myavana-share-target-label').textContent = 'Copy link'; }, 1800);
                    track('copy_link');
                } else {
                    toast('Couldn\'t copy. Press and hold the link to copy it.', 'error');
                }
            } catch (err) {
                toast((err && err.message) || 'We couldn\'t create a link for this.', 'error');
            }
            return;
        }

        if (key === 'more') {
            try {
                const u = await url();
                persistCaption();
                await navigator.share({ title: ctx.title || 'My hair journey', text, url: u });
                track('native');
            } catch (err) { /* cancelled */ }
            return;
        }

        const builders = {
            facebook: (u) => `https://www.facebook.com/sharer/sharer.php?u=${enc(u)}`,
            whatsapp: (u) => `https://wa.me/?text=${enc(`${text} ${u}`)}`,
            messenger: (u) => `fb-messenger://share/?link=${enc(u)}`,
            x: (u) => `https://twitter.com/intent/tweet?text=${enc(text)}&url=${enc(u)}`,
            threads: (u) => `https://www.threads.net/intent/post?text=${enc(`${text} ${u}`)}`,
            pinterest: (u) => `https://pinterest.com/pin/create/button/?url=${enc(u)}&media=${enc(ctx.image || '')}&description=${enc(text)}`,
            linkedin: (u) => `https://www.linkedin.com/sharing/share-offsite/?url=${enc(u)}`,
            email: (u) => `mailto:?subject=${enc(ctx.title || 'My hair journey')}&body=${enc(`${text}\n\n${u}`)}`,
            sms: (u) => `sms:?&body=${enc(`${text} ${u}`)}`,
        };
        if (!builders[key]) return;
        if (key === 'email' || key === 'sms' || key === 'messenger') {
            try { const u = await url(); persistCaption(); window.location.href = builders[key](u); track(key); } catch (err) { toast('We couldn\'t create a link for this.', 'error'); }
            return;
        }
        openWindow(builders[key]);
        track(key);
    }

    // ------------------------------------------------------------ Helpers for common things

    const shareBase = () => ((window.myavanaNextData && window.myavanaNextData.homeUrl) || `${window.location.origin}/`);

    /** A public Community post (link is public; no request needed). */
    function post(p) {
        const u = new URL(shareBase());
        u.searchParams.set('mhj_share', `p${p.id}`);
        open(Object.assign({ heading: 'Share this post', url: u.toString() }, p));
    }

    /** One of her journey entries: link created (and caption saved) on share. */
    function entry(e) {
        const video = (e.videos && e.videos[0]) || null;
        open({
            heading: 'Share your moment',
            title: e.title || 'My hair journey',
            text: e.notes || '',
            image: (e.photos && e.photos[0]) || e.featuredImage || (video && video.poster) || '',
            video: video ? video.url : '',
            editableCaption: true,
            getUrl: (cap) => MyavanaNext.API.post('share/link', { type: 'entry', id: e.id, caption: cap }).then((d) => d.url),
            community: () => MyavanaNext.API.post(`journal/entries/${e.id}/community`, {}),
        });
    }

    return { open, close, post, entry };
})();
