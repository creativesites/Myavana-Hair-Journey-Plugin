/**
 * Mya Chat Widget Embed Adapter for MYAVANA Next (myhairjourney.ai)
 *
 * Two jobs:
 *
 * 1. Page awareness. The Hair Journey shell is a single page routed entirely
 *    by hash (https://myhairjourney.ai/#routine). `pushState` does not fire
 *    `popstate`, so the app calls MyavanaNext.Mya.syncRoute() on every
 *    navigation; the listeners here only cover cold loads and back/forward.
 *
 * 2. The local platform bridge. Chat, live voice and history are global
 *    MYAVANA capabilities. Stories and Profile are local to this platform —
 *    they render this site's own journal entries, goals and strand record —
 *    so we register a provider that serves them from the authenticated REST
 *    API. The widget shows those tabs only because this provider exists.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Mya = (function () {
    'use strict';

    var DEFAULT_CHAT_BASE = 'http://localhost:8080';
    var VALID_ROUTES = ['home', 'today', 'journey', 'routine', 'community', 'profile'];

    // The journey record is a single fairly wide REST response, so it is
    // cached briefly: opening Stories then Profile shouldn't cost two calls.
    var CACHE_TTL_MS = 60000;
    var journeyCache = null;
    var journeyCachedAt = 0;
    var inFlight = null;

    function settings() {
        return window.myavanaNextData || window.myavanaSettings || {};
    }

    function getApiBase() {
        return settings().chatApiBase || DEFAULT_CHAT_BASE;
    }

    function getRestUrl(path) {
        var base = settings().restUrl || '/wp-json/myavana/v1/';
        return base.replace(/\/$/, '/') + path.replace(/^\//, '');
    }

    function getNonce() {
        return settings().nonce || (window.wpApiSettings && window.wpApiSettings.nonce) || '';
    }

    function isLoggedIn() {
        return !!settings().isLoggedIn;
    }

    // ---- Page awareness ----

    /**
     * Resolve the active tab. The shell keeps the current tab in its store, so
     * prefer that; the hash is authoritative on a cold load before the store
     * has been populated.
     */
    function currentRoute() {
        if (MyavanaNext.Store && typeof MyavanaNext.Store.get === 'function') {
            var stored = MyavanaNext.Store.get('currentTab');
            if (stored && VALID_ROUTES.indexOf(stored) !== -1) {
                return stored;
            }
        }

        var hash = (window.location.hash || '').replace(/^#!?/, '').split('?')[0].toLowerCase();
        return VALID_ROUTES.indexOf(hash) !== -1 ? hash : 'home';
    }

    function buildPageContext(route) {
        route = route || currentRoute();
        return {
            surface: 'myhairjourney.ai',
            page: (window.location.pathname || '/') + '#' + route,
            route: route,
            view: route,
            title: document.title || 'Myavana Hair Journey'
        };
    }

    /**
     * Push the current route into the widget. Called by app.js on every
     * in-app navigation, and by the hashchange/popstate listeners below.
     */
    function syncRoute(route) {
        if (!window.MyavanaWidget || !window.MyavanaWidget.setContext) return;
        window.MyavanaWidget.setContext(buildPageContext(route));
    }

    // ---- Local platform provider ----

    function restGet(path) {
        var headers = { 'Accept': 'application/json' };
        var nonce = getNonce();
        if (nonce) headers['X-WP-Nonce'] = nonce;

        return fetch(getRestUrl(path), {
            headers: headers,
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) throw new Error('REST ' + path + ' returned ' + res.status);
            return res.json();
        }).then(function (json) {
            // Match api.js's contract: treat an explicit success:false as a
            // failure even on a 200. Every route currently pairs failure with
            // a non-2xx status, but a 200 carrying no `data` would otherwise
            // surface as a journey record of undefined fields — which renders
            // exactly like a working sync.
            if (json && json.success === false) {
                throw new Error(json.message || ('REST ' + path + ' reported failure'));
            }
            return json;
        });
    }

    /**
     * The journey record backing the Stories and Profile tabs.
     *
     * /profile already returns entries, goals, routines, badges, stats and
     * analytics in one response, so this is a single round trip. It resolves
     * to null for a signed-out visitor, which the widget renders as its
     * signed-out state rather than inventing a profile.
     */
    function getJourney() {
        if (!isLoggedIn()) {
            return Promise.reject(new Error('Not signed in'));
        }

        var fresh = journeyCache && (Date.now() - journeyCachedAt) < CACHE_TTL_MS;
        if (fresh) return Promise.resolve(journeyCache);
        if (inFlight) return inFlight;

        inFlight = restGet('profile')
            .then(function (res) {
                // RestController wraps payloads as { success, data: {...} }.
                var data = (res && res.data) ? res.data : res;
                journeyCache = data;
                journeyCachedAt = Date.now();
                inFlight = null;
                return data;
            })
            .catch(function (err) {
                inFlight = null;
                throw err;
            });

        return inFlight;
    }

    function invalidateJourney() {
        journeyCache = null;
        journeyCachedAt = 0;
    }

    /**
     * Open this platform's own entry composer rather than staging a photo in
     * the chat, so the entry lands in the journal with its real metadata
     * (type, moisture, length, products) attached.
     */
    function openEntryComposer() {
        // SmartEntry is the shell's real entry composer, so the entry lands in
        // the journal with its full metadata (type, photos, moisture, length,
        // products) rather than as a bare photo stapled to a chat message.
        if (MyavanaNext.SmartEntry && typeof MyavanaNext.SmartEntry.open === 'function') {
            // SmartEntry calls Mya.invalidateJourney() once the entry
            // actually saves, so nothing to drop here.
            MyavanaNext.SmartEntry.open();
            return;
        }

        navigate('journey');
    }

    function navigate(tab, params) {
        var target = VALID_ROUTES.indexOf(tab) !== -1 ? tab : 'today';

        if (MyavanaNext.App && typeof MyavanaNext.App.navigate === 'function') {
            MyavanaNext.App.navigate(target);
        } else {
            window.location.hash = target;
        }

        if (params && params.entryId) {
            window.setTimeout(function () {
                var node = document.querySelector('[data-entry-id="' + params.entryId + '"]');
                if (node && node.scrollIntoView) {
                    node.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 320);
        }
    }

    function registerLocalPlatform() {
        if (!window.MyavanaWidget) return;

        // registerPlatformAdapter is the canonical SDK name (Astra owns the
        // widget surface); registerLocalPlatform is the earlier alias, kept as
        // a fallback so an older bundle still wires up.
        var register = window.MyavanaWidget.registerPlatformAdapter ||
                       window.MyavanaWidget.registerLocalPlatform;
        if (!register) return;

        register.call(window.MyavanaWidget, {
            name: 'Myavana Hair Journey',
            platform: 'wordpress',
            getJourney: getJourney,
            openEntryComposer: openEntryComposer,
            navigate: navigate
        });
    }

    // ---- Boot ----

    function init() {
        if (!window.MyavanaWidget) {
            console.warn('[Mya Widget] window.MyavanaWidget not loaded yet.');
            return;
        }

        try {
            window.MyavanaWidget.init({
                apiBase: getApiBase(),
                position: 'bottom-left',
                context: buildPageContext()
            });
        } catch (err) {
            console.error('[Mya Widget] Initialization error:', err);
            return;
        }

        // Only a signed-in member has a journey to show, so the Stories and
        // Profile tabs stay hidden for guests.
        if (isLoggedIn()) {
            registerLocalPlatform();
        }

        var nonce = getNonce();
        if (!nonce) return;

        fetch(getRestUrl('auth/chat-token'), {
            headers: { 'X-WP-Nonce': nonce },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res && res.success && res.data && res.data.token) {
                    window.MyavanaWidget.setContext({
                        chatToken: res.data.token,
                        wpUserId: res.data.userId,
                        userName: res.data.userName,
                        firstName: res.data.firstName || res.data.userName
                    });
                }
            })
            .catch(function () { /* anonymous/guest fallback */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Cold loads and browser back/forward. In-app navigation goes through
    // syncRoute() from app.js, because pushState fires neither of these.
    window.addEventListener('hashchange', function () { syncRoute(); });
    window.addEventListener('popstate', function () { syncRoute(); });

    return {
        open: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.open) window.MyavanaWidget.open();
        },
        close: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.close) window.MyavanaWidget.close();
        },
        startLiveVoice: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.startLiveVoice) window.MyavanaWidget.startLiveVoice();
        },
        endLiveVoice: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.endLiveVoice) window.MyavanaWidget.endLiveVoice();
        },
        openProfile: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.openProfile) window.MyavanaWidget.openProfile();
        },
        openStories: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.openStories) window.MyavanaWidget.openStories();
        },
        openHistory: function () {
            if (window.MyavanaWidget && window.MyavanaWidget.openHistory) window.MyavanaWidget.openHistory();
        },
        switchView: function (view) {
            if (window.MyavanaWidget && window.MyavanaWidget.switchView) window.MyavanaWidget.switchView(view);
        },
        ask: function (prompt) {
            if (!window.MyavanaWidget) return;
            window.MyavanaWidget.open();
            if (prompt) window.MyavanaWidget.sendMessage(prompt);
        },
        syncRoute: syncRoute,
        currentRoute: currentRoute,
        // Call after the member creates or edits an entry, goal or profile
        // field so Stories and Profile don't serve a stale copy.
        invalidateJourney: function () {
            invalidateJourney();
            if (window.MyavanaWidget && window.MyavanaWidget.refreshJourney) {
                window.MyavanaWidget.refreshJourney();
            }
        },
        setPageContext: function (page, extra) {
            if (window.MyavanaWidget && window.MyavanaWidget.setContext) {
                window.MyavanaWidget.setContext(Object.assign(buildPageContext(), { page: page }, extra || {}));
            }
        }
    };
})();
