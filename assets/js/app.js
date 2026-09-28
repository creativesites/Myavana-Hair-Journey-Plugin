/**
 * MYAVANA Next - Main Application Shell & SPA Router
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.App = (function() {
    'use strict';

    const validTabs = ['home', 'today', 'journey', 'routine', 'community', 'profile'];
    let booted = false;

    function init() {
        // Each module boots in isolation: one throwing must not leave every
        // later view (Today included) stuck on its loading skeleton.
        ['SmartEntry', 'Today', 'Journey', 'Routine', 'Community', 'Profile', 'Auth', 'Onboarding', 'SignupWelcome'].forEach(name => {
            const mod = MyavanaNext[name];
            if (!mod || typeof mod.init !== 'function') return;
            try {
                mod.init();
            } catch (e) {
                console.error(`[MYAVANA] ${name} failed to start`, e);
            }
        });

        bindNavigation();
        bindGlobalActions();
        booted = true;

        // Guests begin on the complete public homepage. Community and auth
        // are sibling views; the welcome prompt is an overlay, never a route.
        if (!document.querySelector('#view-today')) {
            const initialHash = window.location.hash.replace('#', '').toLowerCase();
            const targetId = initialHash === 'community' ? 'view-community' : initialHash === 'auth' ? 'view-auth' : 'view-home';

            document.querySelectorAll('.myavana-next-view').forEach(view => {
                const isTarget = view.id === targetId;
                view.classList.toggle('active', isTarget);
                view.style.display = isTarget ? 'block' : 'none';
            });

            if (targetId === 'view-auth' && MyavanaNext.Auth) {
                MyavanaNext.Auth.open('signin');
            }
            initWelcomePrompt();
            return;
        }

        // Handle initial URL hash or default from shell attribute / 'today'
        const rootEl = document.querySelector('#myavana-next-root');
        const defaultTab = rootEl ? (rootEl.getAttribute('data-default-tab') || 'today') : 'today';
        const initialHash = window.location.hash.replace('#', '').toLowerCase();
        const startTab = validTabs.includes(initialHash) ? initialHash : (validTabs.includes(defaultTab) ? defaultTab : 'today');
        navigate(startTab, false);
    }

    function navigate(tabName, updateHistory = true) {
        if (!validTabs.includes(tabName)) {
            tabName = 'today';
        }

        // Update Store
        MyavanaNext.Store.set('currentTab', tabName);

        // Update View Containers
        document.querySelectorAll('.myavana-next-view').forEach(view => {
            if (view.id === `view-${tabName}`) {
                view.classList.add('active');
                view.style.display = 'block';
            } else {
                view.classList.remove('active');
                view.style.display = 'none';
            }
        });

        // Update Desktop Navigation Links
        document.querySelectorAll('.myavana-next-nav-link, [data-home-nav]').forEach(link => {
            if (link.getAttribute('data-tab') === tabName) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // Update Mobile Bottom Navigation Tab Items
        document.querySelectorAll('.myavana-next-tab-item').forEach(item => {
            if (item.getAttribute('data-tab') === tabName) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        if (updateHistory) {
            window.history.pushState(null, '', `#${tabName}`);
        }

        // Trigger view refresh if available
        if (tabName === 'today' && MyavanaNext.Today) MyavanaNext.Today.refresh();
        if (tabName === 'journey' && MyavanaNext.Journey) MyavanaNext.Journey.refresh();
        if (tabName === 'routine' && MyavanaNext.Routine) MyavanaNext.Routine.refresh();
        if (tabName === 'community' && MyavanaNext.Community) MyavanaNext.Community.refresh();
        if (tabName === 'profile' && MyavanaNext.Profile) MyavanaNext.Profile.refresh();

        // Keep Mya page-aware. The shell routes with pushState, which fires
        // neither hashchange nor popstate, so the widget would otherwise stay
        // pinned to whichever tab happened to be open when it booted.
        if (MyavanaNext.Mya && MyavanaNext.Mya.syncRoute) {
            MyavanaNext.Mya.syncRoute(tabName);
        }

        // Smooth scroll to top of app
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function bindNavigation() {
        // Desktop Top Nav Links
        document.querySelectorAll('.myavana-next-nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = link.getAttribute('data-tab');
                if (tab) {
                    navigate(tab);
                    const panel = link.getAttribute('data-routine-panel');
                    if (tab === 'routine' && panel && MyavanaNext.Routine) {
                        MyavanaNext.Routine.selectTab(panel);
                    }
                }
            });
        });

        // Home page shortcuts into the app (buttons, cards, "View all").
        document.addEventListener('click', (e) => {
            const link = e.target.closest('[data-home-nav][data-tab]');
            if (!link) return;
            const tab = link.getAttribute('data-tab');
            if (!validTabs.includes(tab)) return;
            e.preventDefault();
            navigate(tab);
            const panel = link.getAttribute('data-routine-panel');
            if (tab === 'routine' && panel && MyavanaNext.Routine) {
                MyavanaNext.Routine.selectTab(panel);
            }
        });

        // Mobile Bottom Tab Items
        document.querySelectorAll('.myavana-next-tab-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = item.getAttribute('data-tab');
                if (!tab) return;
                navigate(tab);
                const panel = item.getAttribute('data-routine-panel');
                if (tab === 'routine' && panel && MyavanaNext.Routine) {
                    MyavanaNext.Routine.selectTab(panel);
                }
            });
        });

        // Floating Action Button (FAB) -> Open Quick Check-in Modal
        document.querySelectorAll('.btn-open-smart-entry, .myavana-next-tab-fab').forEach(fab => {
            fab.addEventListener('click', (e) => {
                e.preventDefault();
                if (MyavanaNext.SmartEntry) {
                    MyavanaNext.SmartEntry.open('quick');
                }
            });
        });

        // Browser back/forward navigation
        window.addEventListener('popstate', () => {
            const hash = window.location.hash.replace('#', '').toLowerCase();
            if (validTabs.includes(hash)) {
                navigate(hash, false);
            }
        });
    }

    function bindGlobalActions() {
        // Primary Myavana Chat triggers
        document.querySelectorAll('.btn-open-mya').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (window.MyavanaWidget && typeof window.MyavanaWidget.open === 'function') {
                    window.MyavanaWidget.open();
                } else if (window.MyavanaNext && window.MyavanaNext.Mya && typeof window.MyavanaNext.Mya.open === 'function') {
                    window.MyavanaNext.Mya.open();
                }
            });
        });

        bindAccountDropdown();
    }

    function initWelcomePrompt() {
        const modal = document.querySelector('#myavana-welcome-modal');
        if (!modal || localStorage.getItem('myavana_welcome_prompt_seen')) return;

        const close = () => {
            modal.hidden = true;
            localStorage.setItem('myavana_welcome_prompt_seen', '1');
        };
        const open = () => {
            modal.hidden = false;
            modal.querySelector('[data-welcome-goal]')?.focus();
        };

        modal.querySelectorAll('[data-close-welcome]').forEach(button => button.addEventListener('click', close));
        modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) close(); });
        modal.querySelectorAll('[data-welcome-goal]').forEach(button => {
            button.addEventListener('click', () => {
                modal.querySelectorAll('[data-welcome-goal]').forEach(item => item.classList.remove('selected'));
                button.classList.add('selected');
                modal.querySelector('.myavana-welcome-preview span').textContent = `${button.dataset.welcomeGoal}: a tailored routine, private photo timeline, and guidance that evolves with your entries.`;
            });
        });
        modal.querySelector('[data-open-auth]')?.addEventListener('click', close);
        window.setTimeout(open, 16000);
    }

    function bindAccountDropdown() {
        const trigger = document.querySelector('#myavana-account-trigger');
        const dropdown = document.querySelector('#myavana-account-dropdown');
        if (!trigger || !dropdown) return;

        function close() {
            dropdown.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }

        function open() {
            dropdown.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.hidden ? open() : close();
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== trigger) {
                close();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !dropdown.hidden) close();
        });

        dropdown.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', close);
        });
    }

    return {
        init,
        navigate,
        isBooted: () => booted,
    };
})();

// Alias for Router
window.MyavanaNext.Router = window.MyavanaNext.App;

// Start-up progress, read by the inline boot watchdog when the app fails to start.
window.MyavanaNextBoot = { stage: 'script-loaded', readyStateAtLoad: document.readyState };

// Boot once the DOM is parsed. If this script runs after DOMContentLoaded
// has already fired (browser extensions can shift script timing), start now
// rather than waiting for an event that will never come.
(function bootWhenReady() {
    const boot = () => {
        if (!document.querySelector('.myavana-next-shell')) {
            window.MyavanaNextBoot.stage = 'no-shell';
            return;
        }
        window.MyavanaNextBoot.stage = 'init-started';
        MyavanaNext.App.init();
        window.MyavanaNextBoot.stage = 'init-finished';
        renderIcons();
    };
    // Server-rendered markup (Home, cards) uses <i data-lucide>; the icon
    // library may load after this script, so try now and again on load.
    const renderIcons = () => {
        try {
            if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
        } catch (e) { /* icons are decoration */ }
    };
    window.addEventListener('load', renderIcons);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
