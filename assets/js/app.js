/**
 * MYAVANA Next - Main Application Shell & SPA Router
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.App = (function() {
    'use strict';

    const validTabs = ['home', 'today', 'journey', 'routine', 'community', 'profile'];

    function init() {
        // Initialize sub-modules
        if (MyavanaNext.SmartEntry) MyavanaNext.SmartEntry.init();
        if (MyavanaNext.Today) MyavanaNext.Today.init();
        if (MyavanaNext.Journey) MyavanaNext.Journey.init();
        if (MyavanaNext.Routine) MyavanaNext.Routine.init();
        if (MyavanaNext.Community) MyavanaNext.Community.init();
        if (MyavanaNext.Profile) MyavanaNext.Profile.init();
        if (MyavanaNext.Auth) MyavanaNext.Auth.init();

        bindNavigation();
        bindGlobalActions();

        // The authenticated tabs (today/journey/routine/community/profile)
        // only exist in the DOM when logged in. For a logged-out visitor
        // there's no #view-today to navigate to, and calling navigate()
        // would hide every .myavana-next-view (discovery, auth) looking for
        // a tab that doesn't exist, leaving a blank page. Auth.js owns
        // switching between discovery/auth for logged-out visitors instead.
        if (!document.querySelector('.myavana-next-view')) {
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
        document.querySelectorAll('.myavana-next-nav-link').forEach(link => {
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

        // Mobile Bottom Tab Items
        document.querySelectorAll('.myavana-next-tab-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = item.getAttribute('data-tab');
                if (tab) navigate(tab);
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
        // Kommunicate chat triggers
        document.querySelectorAll('.btn-open-kommunicate').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (MyavanaNext.Kommunicate) {
                    MyavanaNext.Kommunicate.open();
                }
            });
        });

        bindAccountDropdown();
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
    };
})();

// Alias for Router
window.MyavanaNext.Router = window.MyavanaNext.App;

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.myavana-next-shell')) {
        MyavanaNext.App.init();
    }
});
