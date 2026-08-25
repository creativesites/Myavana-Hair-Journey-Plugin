/**
 * MYAVANA Next - Reactive Event-Driven State Store
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Store = (function() {
    'use strict';

    const state = {
        currentTab: 'today',
        isLoggedIn: false,
        user: null,
        today: null,
        stats: null,
        journey: {
            currentFilter: 'all',
            currentView: 'timeline', // 'timeline' | 'compare' | 'heatmap'
            timeline: { items: [], total: 0, page: 1 },
            photoEntries: [],
            heatmap: [],
        },
        routine: {
            routines: [],
            checklist: null,
            cabinet: [],
        },
        goals: {
            active: [],
            completed: [],
        },
        community: {
            currentTab: 'for_you',
            feed: [],
            twins: [],
        },
        profile: null,
        aiDrawerOpen: false,
        smartEntryOpen: false,
        smartEntryMode: 'quick', // 'quick' | 'wash_day'
    };

    const subscribers = [];

    function getState() {
        return state;
    }

    function set(key, value) {
        state[key] = value;
        notify(key, value);
    }

    function update(partial) {
        Object.assign(state, partial);
        notify('bulk', partial);
    }

    function subscribe(callback) {
        subscribers.push(callback);
        return function unsubscribe() {
            const idx = subscribers.indexOf(callback);
            if (idx > -1) subscribers.splice(idx, 1);
        };
    }

    function notify(key, value) {
        subscribers.forEach(cb => {
            try {
                cb(key, value, state);
            } catch (err) {
                console.error('[MYAVANA Store Error]', err);
            }
        });
        document.dispatchEvent(new CustomEvent('myavana:state:changed', {
            detail: { key, value, state }
        }));
    }

    return {
        getState,
        set,
        update,
        subscribe,
    };
})();
