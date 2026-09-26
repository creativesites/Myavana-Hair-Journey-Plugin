/**
 * MYAVANA Community — visitor (logged-out) experience layer.
 *
 * The feed markup itself is produced by social-feed.js, which is shared with
 * the member view. Rather than fork that renderer, this module observes the
 * cards as they land and enhances them for visitors:
 *
 *   - branded monogram avatars when a member has no Gravatar
 *   - media that fades in once decoded, and opens in a lightbox
 *   - a join card seeded a few posts into the feed
 *   - double-tap-to-like as the conversion moment
 *   - skeleton placeholders while the first page loads
 *   - infinite scroll in place of a "load more" button
 *
 * Everything here is inert unless myavanaCommunitySettings.isGuest is set,
 * so the member feed is untouched.
 *
 * @package Myavana\Next
 */

(function () {
    'use strict';

    const settings = window.myavanaCommunitySettings || {};
    if (!settings.isGuest) return;

    const GRID_ID = 'myavana-feed-grid';
    const CTA_AFTER_NTH_POST = 3;

    /* ------------------------------------------------------------------ */
    /* Avatars                                                             */
    /* ------------------------------------------------------------------ */

    // Deliberately desaturated brand-adjacent tones: a monogram should read
    // as part of the page, not as a colour-wheel sticker on top of it.
    const MONOGRAM_COLORS = [
        '#C98C72', '#8C7A70', '#7D8B7A', '#A8776B',
        '#6F7789', '#B08968', '#7C6A78', '#5F7470'
    ];

    function monogramColor(seed) {
        let hash = 0;
        const text = String(seed || '');
        for (let i = 0; i < text.length; i++) {
            hash = (hash << 5) - hash + text.charCodeAt(i);
            hash |= 0;
        }
        return MONOGRAM_COLORS[Math.abs(hash) % MONOGRAM_COLORS.length];
    }

    function initialsFrom(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return 'M';
        if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function replaceWithMonogram(img) {
        if (!img || !img.parentNode) return;
        const name = img.getAttribute('alt') || 'Member';
        const badge = document.createElement('div');
        badge.className = 'myavana-guest-avatar-fallback';
        badge.setAttribute('aria-hidden', 'true');
        badge.textContent = initialsFrom(name);
        badge.style.background = monogramColor(name + (img.dataset.userId || ''));
        img.parentNode.replaceChild(badge, img);
    }

    function enhanceAvatar(img) {
        if (img.dataset.guestAvatar === '1') return;
        img.dataset.guestAvatar = '1';

        // Ask Gravatar for a 404 rather than its grey silhouette, so "this
        // member has no avatar" becomes a load error we can style around.
        try {
            const url = new URL(img.src, window.location.origin);
            if (/gravatar\.com$/i.test(url.hostname) || /gravatar\.com/i.test(url.href)) {
                url.searchParams.set('d', '404');
                url.searchParams.set('s', '160');
                img.src = url.toString();
            }
        } catch (e) {
            /* A malformed avatar URL just falls through to the error path. */
        }

        img.addEventListener('error', () => replaceWithMonogram(img), { once: true });
        if (img.complete && img.naturalWidth === 0) replaceWithMonogram(img);
    }

    /* ------------------------------------------------------------------ */
    /* Media                                                               */
    /* ------------------------------------------------------------------ */

    function enhanceMedia(card) {
        const wrapper = card.querySelector('.myavana-post-image-wrapper');
        if (wrapper && wrapper.dataset.guestMedia !== '1') {
            wrapper.dataset.guestMedia = '1';
            const img = wrapper.querySelector('img');
            if (img) {
                const markLoaded = () => { wrapper.dataset.loaded = '1'; };
                if (img.complete && img.naturalWidth > 0) {
                    markLoaded();
                } else {
                    img.addEventListener('load', markLoaded, { once: true });
                    img.addEventListener('error', markLoaded, { once: true });
                }
                wrapper.setAttribute('role', 'button');
                wrapper.setAttribute('tabindex', '0');
                wrapper.setAttribute('aria-label', 'View photo full size');
            }
        }

        // Tag the card with its media kind so text-only posts can take the
        // typographic treatment instead of looking like a card with a gap.
        if (!card.dataset.media) {
            if (card.querySelector('.myavana-post-video-wrapper')) card.dataset.media = 'video';
            else if (wrapper) card.dataset.media = 'image';
            else card.dataset.media = 'text';
        }
    }

    /* ------------------------------------------------------------------ */
    /* Lightbox                                                            */
    /* ------------------------------------------------------------------ */

    let lightbox = null;
    let lastFocused = null;

    function buildLightbox() {
        if (lightbox) return lightbox;
        lightbox = document.createElement('div');
        lightbox.className = 'myavana-guest-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Photo viewer');
        lightbox.hidden = true;
        lightbox.innerHTML =
            '<button type="button" class="myavana-guest-lightbox-close" aria-label="Close photo">&times;</button>' +
            '<img alt="">';
        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox || event.target.closest('.myavana-guest-lightbox-close')) {
                closeLightbox();
            }
        });
        document.body.appendChild(lightbox);
        return lightbox;
    }

    function openLightbox(src, alt) {
        const box = buildLightbox();
        const img = box.querySelector('img');
        img.src = src;
        img.alt = alt || '';
        lastFocused = document.activeElement;
        box.hidden = false;
        requestAnimationFrame(() => { box.dataset.open = '1'; });
        box.querySelector('.myavana-guest-lightbox-close').focus();
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightbox || lightbox.hidden) return;
        delete lightbox.dataset.open;
        document.body.style.overflow = '';
        window.setTimeout(() => { lightbox.hidden = true; }, 220);
        if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeLightbox();
    });

    /* ------------------------------------------------------------------ */
    /* Conversion moments                                                  */
    /* ------------------------------------------------------------------ */

    function promptSignup() {
        if (window.MyavanaNext && window.MyavanaNext.Auth) {
            window.MyavanaNext.Auth.open('signup');
        }
    }

    function heartBurst(card) {
        const burst = document.createElement('div');
        burst.className = 'myavana-guest-heart-burst';
        burst.innerHTML =
            '<svg viewBox="0 0 24 24" width="92" height="92" fill="currentColor" aria-hidden="true">' +
            '<path d="M12 21s-8-5.2-8-10.6A4.6 4.6 0 0 1 12 7a4.6 4.6 0 0 1 8 3.4C20 15.8 12 21 12 21z"/></svg>';
        card.appendChild(burst);
        window.setTimeout(() => burst.remove(), 950);
    }

    function buildFeedCta() {
        const cta = document.createElement('div');
        cta.className = 'myavana-guest-feed-cta';
        cta.dataset.guestCta = '1';
        cta.innerHTML =
            '<h3>Your turn</h3>' +
            '<p>Track your own progress privately, get a routine built around your goals, ' +
            'and share only what you want to share.</p>' +
            '<a href="#auth" data-open-auth="signup" class="myavana-btn myavana-btn-primary">Create your free account</a>';
        cta.querySelector('a').addEventListener('click', (event) => {
            event.preventDefault();
            promptSignup();
        });
        return cta;
    }

    function placeFeedCta(grid) {
        if (grid.querySelector('[data-guest-cta="1"]')) return;
        const cards = grid.querySelectorAll('.myavana-post-card');
        if (cards.length <= CTA_AFTER_NTH_POST) return;
        const anchor = cards[CTA_AFTER_NTH_POST - 1];
        if (anchor && anchor.nextSibling) {
            grid.insertBefore(buildFeedCta(), anchor.nextSibling);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Skeletons                                                           */
    /* ------------------------------------------------------------------ */

    function skeletonCard() {
        const card = document.createElement('div');
        card.className = 'myavana-guest-skeleton';
        card.dataset.guestSkeleton = '1';
        card.setAttribute('aria-hidden', 'true');
        card.innerHTML =
            '<div class="myavana-guest-skeleton-row">' +
            '<div class="myavana-guest-skeleton-bone myavana-guest-skeleton-avatar"></div>' +
            '<div class="myavana-guest-skeleton-lines">' +
            '<div class="myavana-guest-skeleton-bone" style="height:11px;width:38%"></div>' +
            '<div class="myavana-guest-skeleton-bone" style="height:9px;width:22%"></div>' +
            '</div></div>' +
            '<div class="myavana-guest-skeleton-bone myavana-guest-skeleton-media"></div>' +
            '<div class="myavana-guest-skeleton-foot">' +
            '<div class="myavana-guest-skeleton-bone" style="height:10px;width:70%"></div>' +
            '<div class="myavana-guest-skeleton-bone" style="height:10px;width:45%"></div>' +
            '</div>';
        return card;
    }

    // The skeletons live inside #myavana-feed-loading rather than in the grid:
    // social-feed.js already shows that container while a page is in flight and
    // hides it when the render lands, so borrowing it means the placeholder
    // lifecycle is driven by the real loader instead of racing it (the grid
    // itself gets display:none during a load, which would hide them).
    function installSkeletons() {
        const loader = document.getElementById('myavana-feed-loading');
        if (!loader || loader.dataset.guestSkeleton === '1') return;
        loader.dataset.guestSkeleton = '1';
        loader.innerHTML = '';
        loader.classList.add('myavana-guest-skeleton-stack');
        for (let i = 0; i < 3; i++) loader.appendChild(skeletonCard());
    }

    /* ------------------------------------------------------------------ */
    /* Wiring                                                              */
    /* ------------------------------------------------------------------ */

    function enhanceGrid(grid) {
        grid.querySelectorAll('.myavana-post-avatar').forEach(enhanceAvatar);
        grid.querySelectorAll('.myavana-post-card').forEach(enhanceMedia);
        placeFeedCta(grid);
    }

    function bindGridInteractions(grid) {
        // Media opens the lightbox; this listener sits on the bubble phase so
        // the capture-phase sign-up guard has already decided to let media
        // clicks through.
        grid.addEventListener('click', (event) => {
            const wrapper = event.target.closest('.myavana-post-image-wrapper');
            if (!wrapper) return;
            const img = wrapper.querySelector('img');
            if (img && img.src) openLightbox(img.src, img.alt);
        });

        grid.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            const wrapper = event.target.closest('.myavana-post-image-wrapper');
            if (!wrapper) return;
            event.preventDefault();
            const img = wrapper.querySelector('img');
            if (img && img.src) openLightbox(img.src, img.alt);
        });

        grid.addEventListener('dblclick', (event) => {
            const card = event.target.closest('.myavana-post-card');
            if (!card || event.target.closest('button, a')) return;
            heartBurst(card);
            window.setTimeout(promptSignup, 620);
        });
    }

    function bindInfiniteScroll() {
        const trigger = document.getElementById('myavana-load-more-btn');
        const holder = document.getElementById('myavana-feed-load-more');
        if (!trigger || !holder || !('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                // Only auto-advance while the button is genuinely available;
                // social-feed.js hides its container when the feed is done.
                if (entry.isIntersecting && holder.style.display !== 'none' && !trigger.disabled) {
                    trigger.click();
                }
            });
        }, { rootMargin: '600px 0px' });

        observer.observe(holder);
    }

    /* ------------------------------------------------------------------ */
    /* Sidebar affix                                                       */
    /* ------------------------------------------------------------------ */

    // `position: sticky` is the CSS baseline, but it's a well-known trap: any
    // ancestor with a non-visible overflow breaks it silently, and this
    // theme sets `body { overflow-x: hidden }` — which per spec forces
    // overflow-y to `auto`, turning <body> into its own scroll container.
    // Rather than depend on the whole ancestor chain staying clean, this
    // runs the sidebar as a classic scroll-bound "affix": fixed to the
    // viewport while the feed scrolls past it, then handed off to sit at
    // the bottom of its own column once the (much shorter) sidebar content
    // runs out — so the column never goes blank and never overshoots into
    // the footer.
    function initSidebarAffix() {
        const column = document.querySelector('.myavana-guest-sidebar');
        const inner = document.querySelector('.myavana-guest-sidebar-inner');
        if (!column || !inner) return;

        const TOP_GAP = 88;
        const desktopQuery = window.matchMedia('(min-width: 992px)');
        let ticking = false;

        column.style.position = 'relative';

        function reset() {
            inner.style.position = '';
            inner.style.top = '';
            inner.style.bottom = '';
            inner.style.left = '';
            inner.style.width = '';
        }

        function update() {
            ticking = false;
            if (!desktopQuery.matches) {
                reset();
                return;
            }

            const columnRect = column.getBoundingClientRect();
            const innerHeight = inner.offsetHeight;
            const columnTopAbs = columnRect.top + window.scrollY;
            const columnHeight = column.offsetHeight;
            const scrollPastTop = window.scrollY + TOP_GAP - columnTopAbs;

            if (scrollPastTop <= 0) {
                // Above the column: sits at its natural document position.
                reset();
            } else if (scrollPastTop + innerHeight >= columnHeight) {
                // The sidebar's own content is shorter than the feed and has
                // run out — pin it to the bottom of the column instead of
                // letting it detach and leave blank space beside the feed.
                inner.style.position = 'absolute';
                inner.style.top = '';
                inner.style.bottom = '0';
                inner.style.left = '0';
                inner.style.width = '100%';
            } else {
                // The common case: ride along with the viewport.
                inner.style.position = 'fixed';
                inner.style.top = TOP_GAP + 'px';
                inner.style.bottom = '';
                inner.style.left = columnRect.left + 'px';
                inner.style.width = columnRect.width + 'px';
            }
        }

        function requestUpdate() {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate);
        desktopQuery.addEventListener('change', requestUpdate);

        // The feed grows as more posts load (infinite scroll) or images
        // finish decoding, which changes the column's height — recheck
        // whenever that happens rather than only on user-driven events.
        if ('ResizeObserver' in window) {
            new ResizeObserver(requestUpdate).observe(document.querySelector('.myavana-guest-feed') || column);
        }

        update();
    }

    function init() {
        const grid = document.getElementById(GRID_ID);
        if (!grid) return;

        installSkeletons();
        bindGridInteractions(grid);
        bindInfiniteScroll();
        initSidebarAffix();

        const observer = new MutationObserver(() => enhanceGrid(grid));
        observer.observe(grid, { childList: true });

        // Cards can already be in place if the feed resolved before this ran.
        enhanceGrid(grid);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
