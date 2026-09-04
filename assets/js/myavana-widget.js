/**
 * Mya — Native AI Companion for MYAVANA Hair Journey (myhairjourney.ai)
 *
 * Luxury Mobile-First Floating AI Workspace & Hair Companion:
 *   - Official MYAVANA Brand Script Logo & Crisp Vector SVG Iconography (Zero Emojis)
 *   - Mobile-First Multi-View Navigation:
 *       1. Chat (Google AI Studio Command Center Workspace)
 *       2. Journey Stories (Visual milestone progress reels & photo timeline)
 *       3. Conversation History (Searchable sessions, "+ New Chat", resumption)
 *       4. Profile & Strand DNA (WordPress user data, bottom sheet / modal & quick actions)
 *   - Gemini Live Voice Chat (Web Audio fluid orb, acoustic barge-in, commit)
 *   - Composer Voice Dictation via gemini-3.5-transcribe with domain biasing
 *   - Tier-1 Generative UI Cards (goal_card, today_checklist, hair_profile, consultation)
 *   - Document Your Journey Photo Staging (Preserving HairAI CNN boundary)
 *
 * Public API:
 *   MyavanaWidget.init(options)
 *   MyavanaWidget.setContext(context)
 *   MyavanaWidget.logEvent(eventType, metadata)
 *   MyavanaWidget.open()
 *   MyavanaWidget.close()
 *   MyavanaWidget.toggleExpand()
 *   MyavanaWidget.switchView(viewName)
 *   MyavanaWidget.sendMessage(text)
 *   MyavanaWidget.startLiveVoice()
 *   MyavanaWidget.endLiveVoice()
 *   MyavanaWidget.openProfile()
 */
(function (window, document) {
    'use strict';

    // Bump on every widget change. Logged on init so it is possible to tell
    // at a glance which build a page actually loaded — a stale cache and a
    // not-yet-deployed plugin look identical from the outside otherwise.
    var BUILD = '2026-09-04.5-composer-inline';

    var USER_ID_KEY = 'myavana_widget_user_id';
    var CONVERSATION_ID_KEY = 'myavana_widget_conversation_id';

    var MYA_LOGO_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAfQAAAH0CAYAAADL1t+KAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAE4mlUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSfvu78nIGlkPSdXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQnPz4KPHg6eG1wbWV0YSB4bWxuczp4PSdhZG9iZTpuczptZXRhLyc+CjxyZGY6UkRGIHhtbG5zOnJkZj0naHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyc+CgogPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9JycKICB4bWxuczpBdHRyaWI9J2h0dHA6Ly9ucy5hdHRyaWJ1dGlvbi5jb20vYWRzLzEuMC8nPgogIDxBdHRyaWI6QWRzPgogICA8cmRmOlNlcT4KICAgIDxyZGY6bGkgcmRmOnBhcnNlVHlwZT0nUmVzb3VyY2UnPgogICAgIDxBdHRyaWI6Q3JlYXRlZD4yMDI1LTAyLTIwPC9BdHRyaWI6Q3JlYXRlZD4KICAgICA8QXR0cmliOkV4dElkPmRkMzAxZDhhLWIyZDktNDFhNS05MWZmLTg1ZjJiY2I4MmUxZTwvQXR0cmliOkV4dElkPgogICAgIDxBdHRyaWI6RmJJZD41MjUyNjU5MTQxNzk1ODA8L0F0dHJpYjpGYklkPgogICAgIDxBdHRyaWI6VG91Y2hUeXBlPjI8L0F0dHJpYjpUb3VjaFR5cGU+CiAgICA8L3JkZjpsaT4KICAgPC9yZGY6U2VxPgogIDwvQXR0cmliOkFkcz4KIDwvcmRmOkRlc2NyaXB0aW9uPgoKIDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PScnCiAgeG1sbnM6ZGM9J2h0dHA6Ly9wdXJsLm9yZy9kYy9lbGVtZW50cy8xLjEvJz4KICA8ZGM6dGl0bGU+CiAgIDxyZGY6QWx0PgogICAgPHJkZjpsaSB4bWw6bGFuZz0neC1kZWZhdWx0Jz5Ccm93biBBbmQgV2hpdGUgTWluaW1hbGlzdCBJbml0aWFsIExvZ28gLSAxPC9yZGY6bGk+CiAgIDwvcmRmOkFsdD4KICA8L2RjOnRpdGxlPgogPC9yZGY6RGVzY3Jp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0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIEDQASBA0AEgQNABIOBfuXcxCFjPQdcAAAAASUVORK5CYII=';

    var COLORS = {
        onyx: '#222323',
        onyxSoft: '#2e2f30',
        onyxDark: '#18191a',
        stone: '#f5f5f7',
        stoneAlt: '#ebebed',
        sand: '#eeece1',
        coral: '#e7a690',
        coralDark: '#d4956f',
        lightCoral: '#fce5d7',
        blueberry: '#4a4d68',
        muted: '#6e6e73',
        emerald: '#2e7d32',
        emeraldBg: '#e8f5e9',
        border: 'rgba(34, 35, 35, 0.08)',
        // Inputs need a stronger edge than cards do: at 0.08 alpha on a
        // near-white ground the top border was invisible, and the New
        // Conversation button's shadow falling across it made the field look
        // like it had no top edge at all.
        borderInput: 'rgba(34, 35, 35, 0.22)',
        borderHover: 'rgba(34, 35, 35, 0.16)',
        white: '#ffffff'
    };

    // Elegant Vector SVG Icon Library (Zero Emojis)
    var ICONS = {
        chat: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        stories: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor"/></svg>',
        history: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        profile: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        camera: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
        image: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
        journal: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="9" y1="7" x2="15" y2="7"/><line x1="9" y1="11" x2="15" y2="11"/></svg>',
        goal: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
        routine: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="m9 14 2 2 4-4"/></svg>',
        mic: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
        micMute: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>',
        paperclip: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        send: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
        plus: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        close: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        expand: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>',
        compress: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>',
        info: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        check: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        clock: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        stop: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>',
        chevronRight: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',
        search: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
        plusCircle: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        sparkles: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
        weather: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>',
        copy: '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        chevronDown: '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
        messageDots: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>',
        leaf: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>'
    };

    var state = {
        apiBase: '',
        position: 'bottom-left',
        userId: 'candace-demo',
        userName: null,
        streak: 5,
        conversationId: null,
        open: false,
        expanded: false,
        infoOpen: false,
        drawerOpen: false,
        activeView: 'chat', // 'chat' | 'stories' | 'history'
        // Platform Architecture: Global vs Local Capabilities
        platform: 'web', // 'wordpress' | 'web' | 'mobile_sdk' | 'salon_pro' | 'shopify'
        capabilities: {
            chat: true,         // Global Core
            history: true,      // Global Core
            liveVoice: true,    // Global Core
            stories: false,     // Local platform extension (e.g. WordPress Hair Journey)
            profile: false,     // Local platform extension (e.g. WordPress Hair Journey)
            photoJourney: false // Local platform extension (e.g. WordPress Hair Journey)
        },
        localPlatform: null,
        experienceContext: {
            surface: 'myhairjourney.ai',
            page: '/'
            // No default route here: seeding one would shadow the URL hash on
            // a cold load, so currentRoute() could never fall back to it.
        },
        els: {},
        isStreaming: false,
        stagedPhoto: null,
        audioListening: false,
        activeStatusEl: null,
        // Voice Dictation
        dictationRecorder: null,
        dictationChunks: [],
        dictationStream: null,
        // Live Voice State
        liveVoiceState: 'idle',
        liveVoiceAudioCtx: null,
        liveVoiceAnalyser: null,
        liveVoiceStream: null,
        liveVoiceRecognition: null,
        liveVoiceTimer: null,
        liveVoiceDuration: 0,
        liveVoiceRaf: null,
        liveVoiceMuted: false,
        liveTurns: [],
        liveBlocks: [],
        bargeInFrames: 0,
        // Local platform bridge (WordPress Hair Journey supplies these surfaces)
        localPlatform: null,
        journeyData: null,
        journeyLoading: false,
        journeyError: false,
        // Data Cache
        userProfile: null,
        journeyStories: [],
        conversationList: []
    };

    function safeStorageGet(key) {
        try { return window.localStorage.getItem(key); } catch (e) { return null; }
    }
    function safeStorageSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (e) { /* ignore */ }
    }

    function uuid() {
        if (window.crypto && window.crypto.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0, v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function ensureIdentity() {
        var userId = safeStorageGet(USER_ID_KEY);
        if (!userId) {
            userId = 'candace-demo';
            safeStorageSet(USER_ID_KEY, userId);
        }
        var conversationId = safeStorageGet(CONVERSATION_ID_KEY);
        if (!conversationId) {
            conversationId = uuid();
            safeStorageSet(CONVERSATION_ID_KEY, conversationId);
        }
        state.userId = userId;
        state.conversationId = conversationId;
    }

    function getMyaLogoHtml(size, extraClass) {
        size = size || 26;
        extraClass = extraClass || '';
        return '<span class="mya-logo-badge ' + extraClass + '" style="width:' + size + 'px;height:' + size + 'px;"><img src="' + MYA_LOGO_DATA_URI + '" class="mya-logo-img" style="width:100%;height:100%;border-radius:50%;object-fit:cover;display:block;" alt="MYAVANA" /></span>';
    }

    // ---- CSS Design System (Mobile-First Luxury Styling) ----

    function injectStyles() {
        if (document.getElementById('myavana-widget-styles')) return;
        var style = document.createElement('style');
        style.id = 'myavana-widget-styles';
        style.textContent = [
            '@import url("https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap");',

            // Keyframe Animations
            '@keyframes myaFadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}',
            '@keyframes myaSlideUp{from{opacity:0;transform:translateY(16px) scale(0.98)}to{opacity:1;transform:translateY(0) scale(1)}}',
            '@keyframes myaPulse{0%{box-shadow:0 0 0 0 rgba(231,166,144,0.4)}70%{box-shadow:0 0 0 10px rgba(231,166,144,0)}100%{box-shadow:0 0 0 0 rgba(231,166,144,0)}}',
            '@keyframes myaShimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}',
            '@keyframes myaWave{0%,100%{height:6px}50%{height:18px}}',
            '@keyframes myaSheetSlideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}',

            // Logo Badge & Branding
            '.mya-logo-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:50%;overflow:hidden;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.14);border:1px solid rgba(231,166,144,0.3);background:' + COLORS.onyx + ';}',
            '.mya-logo-badge.glow{box-shadow:0 0 12px rgba(231,166,144,0.5);border-color:' + COLORS.coral + ';}',

            // Launcher Button (Mobile-First Floating Companion Pill)
            '.mya-launcher{position:fixed;bottom:24px;left:24px;height:52px;padding:0 18px 0 10px;border-radius:9999px;',
            'background:' + COLORS.onyx + ';color:#ffffff;border:1px solid rgba(255,255,255,0.14);cursor:pointer;',
            'box-shadow:0 10px 28px rgba(0,0,0,0.2), 0 2px 8px rgba(231,166,144,0.2);',
            'display:flex;align-items:center;gap:10px;font-family:Archivo,-apple-system,sans-serif;font-size:14px;font-weight:600;',
            'z-index:999998;transition:all 0.22s cubic-bezier(0.4,0,0.2,1);outline:none;user-select:none;}',
            '.mya-launcher:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(0,0,0,0.26), 0 4px 14px rgba(231,166,144,0.35);background:' + COLORS.onyxSoft + ';}',
            '.mya-launcher-badge{background:' + COLORS.coral + ';color:' + COLORS.onyx + ';font-size:10px;font-weight:700;padding:2px 7px;border-radius:9999px;text-transform:uppercase;letter-spacing:0.04em;}',
            '.mya-launcher.is-right{left:auto;right:24px;}',
            // Hidden — not display:none — so it animates back on close.
            '.mya-launcher.is-morphed{opacity:0;transform:scale(0.6) translateY(10px);pointer-events:none;',
            'transition:opacity 0.2s ease, transform 0.28s cubic-bezier(0.22,1,0.36,1);}',

            // Main AI Workspace Panel
            '.mya-panel{position:fixed;bottom:20px;left:20px;width:520px;max-width:calc(100vw - 28px);height:min(840px, calc(100vh - 40px));',
            'background:#ffffff;border-radius:24px;box-shadow:0 24px 60px rgba(0,0,0,0.18), 0 8px 24px rgba(231,166,144,0.12);',
            'display:flex;flex-direction:column;overflow:hidden;font-family:Archivo,-apple-system,sans-serif;z-index:999999;',
            'border:1px solid ' + COLORS.border + ';transform-origin:bottom left;',
            'animation:myaPanelIn 0.42s cubic-bezier(0.22,1,0.36,1);',
            'transition:width 0.32s cubic-bezier(0.22,1,0.36,1), height 0.32s cubic-bezier(0.22,1,0.36,1);}',
            '.mya-panel.is-right{transform-origin:bottom right;}',
            // The launcher visually becomes the panel: it scales away from its
            // own corner as the panel grows out of that same point.
            '@keyframes myaPanelIn{from{opacity:0;transform:scale(0.82) translateY(18px);}to{opacity:1;transform:none;}}',
            '.mya-panel.is-right{left:auto;right:24px;}',
            '.mya-panel.is-expanded{width:min(1040px, calc(100vw - 32px));height:calc(100vh - 28px);bottom:14px;left:14px;}',

            // Header (Clean, Premium, Brand Logo)
            '.mya-header{background:#ffffff;color:' + COLORS.onyx + ';padding:9px 14px;border-bottom:1px solid ' + COLORS.border + ';',
            'display:flex;align-items:center;justify-content:space-between;user-select:none;backdrop-filter:blur(10px);}',
            '.mya-header-left{display:flex;align-items:center;gap:8px;}',
            '.mya-header-title{display:flex;align-items:center;gap:6px;font-size:15px;font-weight:700;letter-spacing:-0.01em;}',
            '.mya-context-pill{display:inline-flex;align-items:center;gap:5px;background:' + COLORS.stone + ';color:' + COLORS.blueberry + ';',
            'font-size:11px;font-weight:600;padding:3px 9px;border-radius:9999px;border:1px solid ' + COLORS.border + ';}',
            '.mya-context-dot{width:6px;height:6px;border-radius:50%;background:' + COLORS.coral + ';display:inline-block;}',
            '.mya-header-actions{display:flex;align-items:center;gap:4px;}',
            '.mya-btn-icon{background:transparent;border:none;color:' + COLORS.muted + ';width:32px;height:32px;border-radius:50%;',
            'display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:all 0.15s ease;}',
            '.mya-btn-icon:hover{background:' + COLORS.stone + ';color:' + COLORS.onyx + ';}',
            '.mya-btn-icon.mobile-back{display:none;}',

            // Live Voice Header Action Button
            '.mya-btn-live-voice{background:linear-gradient(135deg,' + COLORS.onyx + ' 0%,' + COLORS.onyxSoft + ' 100%);color:#ffffff;',
            'border:1px solid rgba(231,166,144,0.4);border-radius:9999px;padding:5px 11px;font-size:11.5px;font-weight:600;cursor:pointer;',
            'display:inline-flex;align-items:center;gap:6px;transition:all 0.18s ease;box-shadow:0 2px 6px rgba(0,0,0,0.08);}',
            '.mya-btn-live-voice:hover{background:' + COLORS.coral + ';color:' + COLORS.onyx + ';border-color:' + COLORS.coral + ';transform:translateY(-1px);}',

            // Multi-View Navigation Bar (Chat | Stories | History | Profile)
            '.mya-nav-strip{background:' + COLORS.stone + ';border-bottom:1px solid ' + COLORS.border + ';display:flex;padding:3px 7px;gap:3px;user-select:none;}',
            '.mya-nav-item{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:6px 4px;border-radius:9px;',
            'font-size:11.5px;font-weight:600;color:' + COLORS.muted + ';background:transparent;border:none;cursor:pointer;transition:all 0.16s ease;}',
            '.mya-nav-item:hover{color:' + COLORS.onyx + ';background:rgba(255,255,255,0.6);}',
            '.mya-nav-item.active{color:' + COLORS.onyx + ';background:#ffffff;box-shadow:0 2px 6px rgba(0,0,0,0.04);font-weight:700;}',
            '.mya-nav-dot{width:5px;height:5px;border-radius:50%;background:' + COLORS.coral + ';display:inline-block;}',

            // Views Container
            '.mya-view-container{flex:1;overflow:hidden;position:relative;display:flex;flex-direction:column;}',
            '.mya-view{flex:1;display:none;flex-direction:column;overflow-y:auto;position:relative;scroll-behavior:smooth;}',
            '.mya-view.active{display:flex;}',

            // View 1: Stream (Chat View)
            '.mya-stream{flex:1;overflow-y:auto;padding:18px 16px 8px;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;',
            'background:linear-gradient(180deg,#fbfbfc 0%,' + COLORS.stone + ' 40%,' + COLORS.stone + ' 100%);}',
            '.mya-stream::-webkit-scrollbar{width:7px;}',
            '.mya-stream::-webkit-scrollbar-track{background:transparent;}',
            '.mya-stream::-webkit-scrollbar-thumb{background:rgba(34,35,35,0.14);border-radius:9999px;border:2px solid transparent;background-clip:padding-box;}',
            '.mya-stream::-webkit-scrollbar-thumb:hover{background:rgba(34,35,35,0.26);background-clip:padding-box;}',

            // View 2: Hair Journey Stories View
            '.mya-stories-view{padding:16px;background:' + COLORS.stone + ';gap:16px;}',
            '.mya-stories-reel{display:flex;gap:12px;overflow-x:auto;padding-bottom:10px;scroll-snap-type:x mandatory;}',
            '.mya-story-circle-wrap{display:flex;flex-direction:column;align-items:center;gap:5px;cursor:pointer;flex-shrink:0;scroll-snap-align:start;}',
            '.mya-story-ring{width:60px;height:60px;border-radius:50%;padding:2.5px;background:linear-gradient(135deg,' + COLORS.coral + ',' + COLORS.blueberry + ');display:flex;align-items:center;justify-content:center;transition:transform 0.18s ease;}',
            '.mya-story-circle-wrap:hover .mya-story-ring{transform:scale(1.05);}',
            '.mya-story-avatar{width:100%;height:100%;border-radius:50%;object-fit:cover;border:2px solid #ffffff;}',
            '.mya-story-label{font-size:10.5px;font-weight:600;color:' + COLORS.onyx + ';max-width:68px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
            '.mya-story-add-card{width:60px;height:60px;border-radius:50%;border:2px dashed ' + COLORS.coral + ';background:#ffffff;display:flex;align-items:center;justify-content:center;color:' + COLORS.coralDark + ';cursor:pointer;}',

            // Stories Timeline Card Feed
            '.mya-story-card{background:#ffffff;border:1px solid ' + COLORS.border + ';border-radius:18px;overflow:hidden;margin-bottom:14px;box-shadow:0 4px 16px rgba(0,0,0,0.03);}',
            '.mya-story-card-img{width:100%;height:190px;object-fit:cover;}',
            '.mya-story-card-body{padding:14px;}',
            '.mya-story-card-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;}',

            // View 3: Conversation History View
            '.mya-history-view{padding:16px 16px 22px;background:linear-gradient(180deg,#fbfbfc 0%,' + COLORS.stone + ' 40%);}',
            '.mya-history-view > *{flex-shrink:0;}',
            '.mya-history-toolbar{display:flex;gap:8px;align-items:center;margin-bottom:14px;}',
            '.mya-new-chat-btn{flex:1;background:linear-gradient(160deg,' + COLORS.onyxSoft + ' 0%,' + COLORS.onyx + ' 100%);color:#ffffff;',
            'border:none;border-radius:13px;padding:11px 14px;font-size:12.5px;font-weight:700;display:flex;align-items:center;',
            'justify-content:center;gap:8px;cursor:pointer;font-family:inherit;box-shadow:0 2px 6px rgba(34,35,35,0.12);',
            'transition:transform 0.24s cubic-bezier(0.22,1,0.36,1),box-shadow 0.24s ease,background 0.2s ease;}',
            '.mya-new-chat-btn:hover{background:linear-gradient(160deg,' + COLORS.coralDark + ' 0%,#c2835f 100%);',
            'transform:translateY(-2px);box-shadow:0 10px 24px rgba(212,149,111,0.32);}',
            '.mya-new-chat-btn:active{transform:translateY(0);}',

            '.mya-search-wrap{position:relative;margin-bottom:4px;z-index:1;}',
            '.mya-search-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:' + COLORS.muted + ';pointer-events:none;transition:color 0.18s ease;}',
            '.mya-search-wrap:focus-within svg{color:' + COLORS.coralDark + ';}',
            '.mya-history-search{width:100%;background:#ffffff;border:1.5px solid ' + COLORS.borderInput + ';border-radius:13px;',
            'padding:11px 34px 11px 36px;font-size:12.5px;outline:none;font-family:inherit;color:' + COLORS.onyx + ';',
            // An inset top highlight so the upper edge reads crisply even where
            // the panel background is almost the same white as the field.
            'box-shadow:inset 0 1px 0 rgba(255,255,255,0.9), 0 1px 2px rgba(34,35,35,0.05);',
            'transition:border-color 0.18s ease,box-shadow 0.18s ease;}',
            '.mya-history-search::placeholder{color:' + COLORS.muted + ';}',
            '.mya-history-search:focus{border-color:' + COLORS.coralDark + ';box-shadow:0 0 0 3px rgba(231,166,144,0.22);}',
            '.mya-search-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:transparent;border:none;',
            'width:24px;height:24px;border-radius:50%;color:' + COLORS.muted + ';cursor:pointer;display:none;align-items:center;',
            'justify-content:center;transition:background 0.16s ease,color 0.16s ease;}',
            '.mya-search-clear:hover{background:' + COLORS.stoneAlt + ';color:' + COLORS.onyx + ';}',
            '.mya-search-clear.on{display:flex;}',

            '.mya-history-group-title{font-size:10px;font-weight:800;color:' + COLORS.muted + ';text-transform:uppercase;',
            'letter-spacing:0.09em;margin:16px 0 8px;display:flex;align-items:center;gap:9px;}',
            '.mya-history-group-title::after{content:"";flex:1;height:1px;background:' + COLORS.border + ';}',

            '.mya-history-item{width:100%;text-align:left;font:inherit;background:#ffffff;border:1px solid ' + COLORS.border + ';',
            'border-radius:15px;padding:12px 13px;margin-bottom:7px;display:flex;align-items:center;gap:11px;cursor:pointer;',
            'transition:transform 0.24s cubic-bezier(0.22,1,0.36,1),box-shadow 0.24s ease,border-color 0.24s ease;',
            'animation:myaMsgIn 0.36s cubic-bezier(0.22,1,0.36,1) both;}',
            '.mya-history-item:hover{transform:translateY(-2px);border-color:' + COLORS.borderHover + ';box-shadow:0 10px 24px rgba(34,35,35,0.08);}',
            '.mya-history-item:active{transform:translateY(0) scale(0.995);}',
            '.mya-history-item.current{border-color:' + COLORS.coral + ';background:linear-gradient(150deg,#fffaf7 0%,#ffffff 60%);}',
            '.mya-history-ico{width:34px;height:34px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;',
            'justify-content:center;background:' + COLORS.stone + ';color:' + COLORS.blueberry + ';transition:background 0.22s ease,color 0.22s ease;}',
            '.mya-history-item:hover .mya-history-ico{background:' + COLORS.lightCoral + ';color:' + COLORS.coralDark + ';}',
            '.mya-history-item.current .mya-history-ico{background:' + COLORS.coral + ';color:' + COLORS.onyx + ';}',
            '.mya-history-main{flex:1;min-width:0;}',
            '.mya-history-t{font-size:13px;font-weight:600;color:' + COLORS.onyx + ';white-space:nowrap;overflow:hidden;',
            'text-overflow:ellipsis;letter-spacing:-0.01em;}',
            '.mya-history-meta{font-size:10.5px;color:' + COLORS.muted + ';margin-top:3px;display:flex;align-items:center;gap:6px;}',
            '.mya-history-meta .sep{width:2.5px;height:2.5px;border-radius:50%;background:' + COLORS.muted + ';opacity:0.5;}',
            '.mya-history-chev{color:' + COLORS.muted + ';flex-shrink:0;opacity:0.45;transition:opacity 0.22s ease,transform 0.22s ease;}',
            '.mya-history-item:hover .mya-history-chev{opacity:1;transform:translateX(2px);}',
            '.mya-history-badge{font-size:9.5px;font-weight:800;color:' + COLORS.coralDark + ';text-transform:uppercase;letter-spacing:0.06em;}',

            // Mobile-First Profile Bottom Sheet / Desktop Modal (.mya-profile-sheet)
            '.mya-profile-sheet{position:absolute;inset:0;background:rgba(0,0,0,0.5);z-index:40;display:none;align-items:flex-end;backdrop-filter:blur(4px);}',
            '.mya-profile-sheet-content{width:100%;max-height:85vh;background:#ffffff;border-radius:24px 24px 0 0;padding:16px 20px 24px;',
            'overflow-y:auto;animation:myaSheetSlideUp 0.24s cubic-bezier(0.16,1,0.3,1);box-shadow:0 -10px 30px rgba(0,0,0,0.15);}',
            '.mya-sheet-handle{width:40px;height:4px;background:' + COLORS.sand + ';border-radius:9999px;margin:0 auto 14px;}',
            '.mya-profile-user-card{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid ' + COLORS.border + ';}',
            '.mya-profile-user-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid ' + COLORS.coral + ';}',
            '.mya-profile-stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;}',
            '.mya-profile-action-btn{width:100%;background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:12px;',
            'padding:10px 14px;font-size:12.5px;font-weight:600;color:' + COLORS.onyx + ';text-align:left;margin-bottom:6px;cursor:pointer;',
            'display:flex;align-items:center;justify-content:space-between;transition:all 0.16s ease;}',
            '.mya-profile-action-btn:hover{background:' + COLORS.lightCoral + ';border-color:' + COLORS.coral + ';}',

            // Jump to bottom indicator
            '.mya-jump-btn{position:sticky;bottom:8px;margin:0 auto;display:none;background:' + COLORS.onyx + ';color:#ffffff;',
            'border:none;border-radius:9999px;padding:6px 14px;font-size:11.5px;font-weight:600;cursor:pointer;',
            'box-shadow:0 4px 12px rgba(0,0,0,0.18);z-index:5;animation:myaFadeIn 0.2s ease;}',

            // ---- Messages ----
            // Consecutive turns from the same speaker are grouped: the name
            // row prints once and the bubbles tuck together, so a long thread
            // reads as conversation rather than a stack of labelled boxes.
            '@keyframes myaMsgIn{from{opacity:0;transform:translateY(9px) scale(0.985);}to{opacity:1;transform:none;}}',
            '@keyframes myaDot{0%,60%,100%{transform:translateY(0);opacity:0.45;}30%{transform:translateY(-4px);opacity:1;}}',
            '@keyframes myaCaret{0%,45%{opacity:1;}55%,100%{opacity:0;}}',

            '.mya-msg-row{display:flex;flex-direction:column;margin-bottom:12px;animation:myaMsgIn 0.34s cubic-bezier(0.22,1,0.36,1) both;}',
            '.mya-msg-row.grouped{margin-top:-8px;}',
            '.mya-msg-header{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:' + COLORS.muted + ';margin-bottom:5px;padding:0 3px;letter-spacing:0.01em;}',
            '.mya-msg-time{font-weight:500;font-size:10px;color:' + COLORS.muted + ';opacity:0.75;font-feature-settings:"tnum";}',

            '.mya-msg{position:relative;max-width:86%;padding:11px 15px;border-radius:18px;font-size:13.5px;line-height:1.6;word-break:break-word;}',
            '.mya-msg.user{background:linear-gradient(160deg,' + COLORS.onyxSoft + ' 0%,' + COLORS.onyx + ' 100%);color:#ffffff;',
            'margin-left:auto;border-bottom-right-radius:5px;box-shadow:0 3px 12px rgba(34,35,35,0.16);}',
            '.mya-msg.assistant{background:#ffffff;color:' + COLORS.onyx + ';margin-right:auto;border-bottom-left-radius:5px;',
            'border:1px solid ' + COLORS.border + ';box-shadow:0 3px 14px rgba(34,35,35,0.05);}',
            // Grouped runs square off the seam between adjacent bubbles.
            '.mya-msg-row.grouped .mya-msg.user{border-top-right-radius:7px;}',
            '.mya-msg-row.grouped .mya-msg.assistant{border-top-left-radius:7px;}',

            // Markdown inside an assistant bubble
            '.mya-msg p{margin:0 0 9px;}',
            '.mya-msg p:last-child{margin-bottom:0;}',
            '.mya-msg ul,.mya-msg ol{margin:0 0 9px;padding-left:19px;}',
            '.mya-msg ul:last-child,.mya-msg ol:last-child{margin-bottom:0;}',
            '.mya-msg li{margin-bottom:4px;line-height:1.55;}',
            '.mya-msg li::marker{color:' + COLORS.coralDark + ';font-weight:700;}',
            '.mya-msg h3,.mya-msg h4{margin:12px 0 6px;font-size:13px;font-weight:700;letter-spacing:-0.01em;}',
            '.mya-msg h3:first-child,.mya-msg h4:first-child{margin-top:0;}',
            '.mya-msg strong{font-weight:700;}',
            '.mya-msg code{background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:5px;padding:1px 5px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}',
            '.mya-msg.user code{background:rgba(255,255,255,0.16);border-color:rgba(255,255,255,0.2);}',
            '.mya-msg blockquote{margin:0 0 9px;padding:2px 0 2px 11px;border-left:2.5px solid ' + COLORS.coral + ';color:' + COLORS.blueberry + ';font-style:italic;}',
            '.mya-msg hr{border:none;border-top:1px solid ' + COLORS.border + ';margin:11px 0;}',
            '.mya-msg a{color:' + COLORS.coralDark + ';text-decoration:underline;text-underline-offset:2px;}',
            '.mya-msg.user a{color:' + COLORS.coral + ';}',

            // Streaming caret
            '.mya-caret{display:inline-block;width:2px;height:14px;background:' + COLORS.coralDark + ';border-radius:1px;',
            'vertical-align:-2px;margin-left:2px;animation:myaCaret 1.05s steps(1) infinite;}',

            // Copy affordance, revealed on hover of a finished reply
            '.mya-msg-tools{display:flex;gap:4px;margin:5px 0 0 3px;opacity:0;transform:translateY(-3px);',
            'transition:opacity 0.2s ease,transform 0.2s ease;pointer-events:none;}',
            '.mya-msg-row:hover .mya-msg-tools,.mya-msg-row:focus-within .mya-msg-tools{opacity:1;transform:none;pointer-events:auto;}',
            '.mya-msg-tool{background:transparent;border:1px solid transparent;border-radius:8px;padding:3px 8px;font-size:10.5px;',
            'font-weight:600;color:' + COLORS.muted + ';cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:4px;',
            'transition:background 0.16s ease,color 0.16s ease,border-color 0.16s ease;}',
            '.mya-msg-tool:hover{background:#ffffff;border-color:' + COLORS.border + ';color:' + COLORS.onyx + ';}',
            '.mya-msg-tool.done{color:' + COLORS.emerald + ';}',

            // Day separator
            '.mya-day-sep{display:flex;align-items:center;gap:10px;margin:6px 0 16px;}',
            '.mya-day-sep::before,.mya-day-sep::after{content:"";flex:1;height:1px;background:' + COLORS.border + ';}',
            '.mya-day-sep span{font-size:10px;font-weight:700;color:' + COLORS.muted + ';text-transform:uppercase;letter-spacing:0.08em;}',

            // Thinking indicator (Zero Telemetry: never a function name or stack)
            '.mya-status-indicator{display:inline-flex;align-items:center;gap:9px;padding:8px 14px;background:#ffffff;',
            'border:1px solid ' + COLORS.border + ';border-radius:9999px;font-size:12px;font-weight:500;color:' + COLORS.blueberry + ';',
            'margin-bottom:12px;box-shadow:0 3px 14px rgba(34,35,35,0.05);animation:myaMsgIn 0.3s cubic-bezier(0.22,1,0.36,1) both;}',
            '.mya-status-dots{display:inline-flex;gap:3.5px;align-items:center;}',
            '.mya-status-dots i{width:5px;height:5px;border-radius:50%;background:' + COLORS.coralDark + ';display:block;animation:myaDot 1.25s infinite ease-in-out;}',
            '.mya-status-dots i:nth-child(2){animation-delay:0.16s;}',
            '.mya-status-dots i:nth-child(3){animation-delay:0.32s;}',
            '.mya-status-shimmer{background:linear-gradient(90deg,' + COLORS.blueberry + ' 0%,' + COLORS.coral + ' 50%,' + COLORS.blueberry + ' 100%);',
            'background-size:200% 100%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;animation:myaShimmer 2.5s infinite linear;}',

            // Generative UI Semantic Cards
            '.mya-card{background:#ffffff;border:1px solid ' + COLORS.border + ';border-radius:18px;padding:16px;margin:10px 0;',
            'box-shadow:0 4px 16px rgba(0,0,0,0.04);max-width:94%;animation:myaFadeIn 0.25s ease;}',
            '.mya-card-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}',
            '.mya-pill{display:inline-block;padding:3px 9px;border-radius:9999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;background:' + COLORS.lightCoral + ';color:' + COLORS.onyx + ';}',
            '.mya-card-title{font-size:14.5px;font-weight:700;color:' + COLORS.onyx + ';margin:4px 0 0;letter-spacing:-0.01em;}',

            // Goal Card
            '.mya-goal-card{border-left:4px solid ' + COLORS.coral + ';}',
            '.mya-goal-pct{font-size:20px;font-weight:700;color:' + COLORS.coral + ';font-feature-settings:"tnum";}',
            '.mya-progress-track{width:100%;height:8px;background:' + COLORS.stone + ';border-radius:9999px;overflow:hidden;margin:10px 0;}',
            '.mya-progress-bar{height:100%;background:linear-gradient(90deg,' + COLORS.coral + ',' + COLORS.coralDark + ');border-radius:9999px;transition:width 0.6s cubic-bezier(0.4,0,0.2,1);}',
            '.mya-goal-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:8px 10px;background:' + COLORS.stone + ';border-radius:10px;font-size:11.5px;color:' + COLORS.blueberry + ';margin-top:8px;}',
            '.mya-card-actions{display:flex;gap:8px;margin-top:12px;padding-top:10px;border-top:1px solid ' + COLORS.border + ';flex-wrap:wrap;}',
            '.mya-action-btn{background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:10px;padding:6px 13px;',
            'font-size:11.5px;font-weight:600;cursor:pointer;color:' + COLORS.onyx + ';transition:all 0.15s ease;display:inline-flex;align-items:center;gap:5px;}',
            '.mya-action-btn:hover{background:' + COLORS.sand + ';border-color:' + COLORS.borderHover + ';transform:translateY(-1px);}',
            '.mya-action-btn.primary{background:' + COLORS.onyx + ';color:#ffffff;border-color:' + COLORS.onyx + ';}',
            '.mya-action-btn.primary:hover{background:' + COLORS.coralDark + ';border-color:' + COLORS.coralDark + ';}',

            // Checklist
            '.mya-checklist-item{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;background:' + COLORS.stone + ';',
            'border-radius:12px;margin-bottom:6px;font-size:12.5px;cursor:pointer;transition:all 0.16s ease;user-select:none;border:1px solid transparent;}',
            '.mya-checklist-item:hover{background:' + COLORS.stoneAlt + ';border-color:' + COLORS.border + ';}',
            '.mya-checklist-item.is-done{background:' + COLORS.emeraldBg + ';color:' + COLORS.emerald + ';border-color:rgba(46,125,50,0.15);}',
            '.mya-checklist-item.is-done .mya-check-text{text-decoration:line-through;opacity:0.8;}',
            '.mya-check-box{width:18px;height:18px;border-radius:50%;border:1.5px solid ' + COLORS.muted + ';display:flex;align-items:center;justify-content:center;margin-right:10px;font-size:11px;font-weight:700;transition:all 0.18s ease;flex-shrink:0;}',
            '.mya-checklist-item.is-done .mya-check-box{background:' + COLORS.emerald + ';color:#ffffff;border-color:' + COLORS.emerald + ';}',
            '.mya-synced-badge{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:600;color:' + COLORS.emerald + ';margin-top:8px;}',

            // Consultation Card
            '.mya-consult-card{background:linear-gradient(135deg,' + COLORS.onyx + ' 0%,' + COLORS.blueberry + ' 100%);color:#ffffff;border-radius:18px;padding:18px;margin:10px 0;max-width:94%;box-shadow:0 8px 24px rgba(34,35,35,0.16);}',
            '.mya-consult-card h4{margin:8px 0 6px;font-size:15px;color:#ffffff;font-weight:700;}',
            '.mya-consult-card p{margin:0 0 14px;font-size:12.5px;color:rgba(255,255,255,0.82);line-height:1.5;}',
            '.mya-consult-btn{background:' + COLORS.coral + ';color:' + COLORS.onyx + ';border:none;border-radius:12px;padding:8px 16px;',
            'font-size:12px;font-weight:700;cursor:pointer;transition:all 0.16s ease;display:inline-flex;align-items:center;gap:6px;}',

            // Google AI Studio Command Center Composer Box
            '.mya-composer-box{background:#ffffff;border-top:1px solid ' + COLORS.border + ';padding:8px 14px 12px;position:relative;}',
            '.mya-input-wrapper{background:#ffffff;border:1.5px solid ' + COLORS.borderInput + ';border-radius:18px;padding:8px 10px;',
            'box-shadow:inset 0 1px 0 rgba(255,255,255,0.9), 0 1px 3px rgba(34,35,35,0.05);',
            'display:flex;flex-direction:row;align-items:flex-end;gap:6px;}',
            '.mya-input-wrapper:focus-within{background:#ffffff;border-color:' + COLORS.coral + ';box-shadow:0 0 0 3px rgba(231,166,144,0.18);}',
            '.mya-textarea.is-scrolling{overflow-y:auto;}',
            // Single line at rest. It grows with the content and collapses
            // straight back — a composer that stays tall after one long message
            // permanently steals space from the conversation.
            '.mya-textarea{flex:1;min-width:0;height:24px;min-height:24px;max-height:132px;background:transparent;border:none;outline:none;',
            'overflow-y:hidden;display:block;padding:4px 2px;',
            'font-family:Archivo,-apple-system,sans-serif;font-size:13.5px;line-height:1.45;color:' + COLORS.onyx + ';resize:none;}',
            '.mya-textarea::placeholder{color:' + COLORS.muted + ';}',

            // Composer Action Bar
            '.mya-action-bar{display:flex;align-items:center;justify-content:space-between;}',
            '.mya-action-group-left{display:flex;align-items:center;gap:2px;flex-shrink:0;}',
            '.mya-action-group-right{display:flex;align-items:center;gap:4px;flex-shrink:0;}',
            '.mya-tool-btn{background:transparent;border:none;color:' + COLORS.muted + ';width:30px;height:30px;border-radius:50%;',
            'display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.15s ease;}',
            '.mya-tool-btn:hover{background:rgba(34,35,35,0.06);color:' + COLORS.onyx + ';}',
            '.mya-tool-btn.active{color:' + COLORS.coralDark + ';background:' + COLORS.lightCoral + ';}',
            '.mya-send-btn{width:32px;height:32px;border-radius:50%;background:' + COLORS.onyx + ';color:#ffffff;border:none;flex-shrink:0;',
            'display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.18s ease;}',
            '.mya-send-btn:hover{background:' + COLORS.coralDark + ';transform:scale(1.05);}',
            '.mya-send-btn:disabled{background:' + COLORS.stoneAlt + ';color:' + COLORS.muted + ';cursor:not-allowed;transform:none;}',

            // Attachment Drawer Menu
            '.mya-drawer{position:absolute;bottom:78px;left:16px;background:#ffffff;border:1px solid ' + COLORS.border + ';',
            'border-radius:16px;box-shadow:0 12px 32px rgba(0,0,0,0.14);padding:8px 0;width:240px;display:none;z-index:20;animation:myaFadeIn 0.18s ease;}',
            '.mya-drawer-item{display:flex;align-items:center;gap:10px;padding:10px 16px;font-size:12.5px;font-weight:500;',
            'color:' + COLORS.onyx + ';cursor:pointer;transition:background 0.14s ease;user-select:none;}',
            '.mya-drawer-item:hover{background:' + COLORS.stone + ';}',

            // Gemini Live Voice Chat Overlay
            '.mya-live-overlay{position:absolute;inset:0;z-index:50;background:radial-gradient(circle at 50% 25%, #25262c 0%, #17181b 70%, #101012 100%);',
            'color:#ffffff;display:none;flex-direction:column;justify-content:space-between;padding:18px 20px 24px;overflow:hidden;font-family:Archivo,-apple-system,sans-serif;}',
            '.mya-live-top-bar{display:flex;align-items:center;justify-content:space-between;}',
            '.mya-live-status-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.08);padding:4px 12px;border-radius:9999px;border:1px solid rgba(255,255,255,0.12);font-size:11.5px;font-weight:600;}',
            '.mya-live-dot{width:7px;height:7px;border-radius:50%;background:#4caf50;box-shadow:0 0 8px #4caf50;animation:myaPulse 2s infinite;}',
            '.mya-live-dot.thinking{background:' + COLORS.coral + ';box-shadow:0 0 8px ' + COLORS.coral + ';}',
            '.mya-live-dot.speaking{background:' + COLORS.coral + ';box-shadow:0 0 10px ' + COLORS.coral + ';}',
            '.mya-live-canvas-wrap{position:relative;width:240px;height:240px;display:flex;align-items:center;justify-content:center;cursor:pointer;margin:0 auto;}',
            '.mya-live-canvas{width:240px;height:240px;border-radius:50%;}',
            '.mya-live-state-banner{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.08);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.12);border-radius:9999px;padding:6px 14px;font-size:12px;font-weight:600;margin-top:14px;color:rgba(255,255,255,0.9);}',
            '.mya-live-ticker-card{background:rgba(255,255,255,0.06);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:12px 16px;min-height:56px;max-height:85px;overflow-y:auto;font-size:13px;line-height:1.45;color:#ffffff;margin-bottom:14px;}',
            '.mya-live-controls{display:flex;align-items:center;justify-content:center;gap:14px;}',
            '.mya-live-btn{background:rgba(255,255,255,0.12);color:#ffffff;border:1px solid rgba(255,255,255,0.16);border-radius:9999px;height:44px;padding:0 18px;font-size:12.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}',
            '.mya-live-btn-end{background:linear-gradient(135deg,#d32f2f 0%,#b71c1c 100%);color:#ffffff;border:none;height:44px;padding:0 20px;border-radius:9999px;font-size:12.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;}',

            // Audio Listening Wave State
            '.mya-audio-wave{display:none;align-items:center;gap:3px;height:20px;margin-left:6px;}',
            '.mya-audio-bar{width:3px;background:' + COLORS.coralDark + ';border-radius:3px;animation:myaWave 0.8s infinite ease-in-out;}',
            '.mya-audio-bar:nth-child(2){animation-delay:0.15s;}',
            '.mya-audio-bar:nth-child(3){animation-delay:0.3s;}',

            // ---- Journey Stories & Profile (local platform surfaces) ----
            '@keyframes myaRise{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:none;}}',
            '@keyframes myaShimmer{0%{background-position:-420px 0;}100%{background-position:420px 0;}}',
            '@keyframes myaRingSpin{to{transform:rotate(360deg);}}',
            '@keyframes myaSheetUp{from{opacity:0;transform:translateY(26px) scale(0.985);}to{opacity:1;transform:none;}}',
            '@keyframes myaFadeIn{from{opacity:0;}to{opacity:1;}}',
            '.mya-rise{animation:myaRise 0.44s cubic-bezier(0.22,1,0.36,1) both;}',
            '.mya-skel{background:linear-gradient(90deg,' + COLORS.stoneAlt + ' 0%,#f7f7f9 50%,' + COLORS.stoneAlt + ' 100%);background-size:840px 100%;animation:myaShimmer 1.5s infinite linear;border-radius:12px;}',

            // Section scaffolding
            '.mya-stories-view > *{flex-shrink:0;}',
            '.mya-section{padding:0 16px;}',
            '.mya-section-title{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;color:' + COLORS.blueberry + ';text-transform:uppercase;letter-spacing:0.07em;margin:18px 0 10px;}',
            '.mya-section-title .mya-rule{flex:1;height:1px;background:' + COLORS.border + ';}',

            // Journey band (real aggregate stats)
            '.mya-band{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:14px 0 2px;}',
            '.mya-band-cell{background:linear-gradient(150deg,#ffffff 0%,' + COLORS.stone + ' 100%);border:1px solid ' + COLORS.border + ';border-radius:16px;padding:11px 10px;text-align:center;transition:transform 0.24s cubic-bezier(0.22,1,0.36,1),box-shadow 0.24s ease;}',
            '.mya-band-cell:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(34,35,35,0.07);}',
            '.mya-band-num{font-size:19px;font-weight:800;color:' + COLORS.onyx + ';letter-spacing:-0.02em;line-height:1.1;}',
            '.mya-band-num small{font-size:11px;font-weight:700;color:' + COLORS.muted + ';margin-left:1px;}',
            '.mya-band-lbl{font-size:9.5px;font-weight:700;color:' + COLORS.muted + ';text-transform:uppercase;letter-spacing:0.05em;margin-top:3px;}',

            // Story reel
            '.mya-reel{display:flex;gap:13px;overflow-x:auto;padding:2px 16px 12px;scroll-snap-type:x mandatory;-ms-overflow-style:none;scrollbar-width:none;}',
            '.mya-reel::-webkit-scrollbar{display:none;}',
            '.mya-reel-item{display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;flex-shrink:0;scroll-snap-align:start;width:66px;background:none;border:none;padding:0;font:inherit;}',
            '.mya-reel-ring{width:62px;height:62px;border-radius:50%;padding:2.5px;background:conic-gradient(from 200deg,' + COLORS.coral + ',' + COLORS.blueberry + ',' + COLORS.coral + ');transition:transform 0.26s cubic-bezier(0.34,1.56,0.64,1);}',
            '.mya-reel-item:hover .mya-reel-ring,.mya-reel-item:focus-visible .mya-reel-ring{transform:scale(1.07);}',
            '.mya-reel-item:active .mya-reel-ring{transform:scale(0.96);}',
            '.mya-reel-img{width:100%;height:100%;border-radius:50%;object-fit:cover;border:2.5px solid #ffffff;display:block;background:' + COLORS.stoneAlt + ';}',
            '.mya-reel-lbl{font-size:10px;font-weight:600;color:' + COLORS.onyx + ';max-width:66px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}',
            '.mya-reel-date{font-size:9px;color:' + COLORS.muted + ';}',
            '.mya-reel-add{width:62px;height:62px;border-radius:50%;border:1.5px dashed ' + COLORS.coral + ';background:' + COLORS.lightCoral + ';display:flex;align-items:center;justify-content:center;color:' + COLORS.coralDark + ';transition:background 0.2s ease,transform 0.26s cubic-bezier(0.34,1.56,0.64,1);}',
            '.mya-reel-item:hover .mya-reel-add{background:#ffffff;transform:scale(1.07) rotate(90deg);}',

            // Timeline
            '.mya-month{font-size:10.5px;font-weight:800;color:' + COLORS.muted + ';text-transform:uppercase;letter-spacing:0.09em;margin:16px 0 9px;}',
            '.mya-entry{width:100%;text-align:left;background:#ffffff;border:1px solid ' + COLORS.border + ';border-radius:18px;overflow:hidden;margin-bottom:11px;cursor:pointer;padding:0;font:inherit;display:block;transition:transform 0.24s cubic-bezier(0.22,1,0.36,1),box-shadow 0.24s ease,border-color 0.24s ease;}',
            '.mya-entry:hover{transform:translateY(-2px);border-color:' + COLORS.borderHover + ';box-shadow:0 12px 28px rgba(34,35,35,0.08);}',
            '.mya-entry:active{transform:translateY(0) scale(0.994);}',
            '.mya-entry-media{position:relative;width:100%;height:172px;overflow:hidden;background:' + COLORS.stoneAlt + ';}',
            '.mya-entry-media img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 0.6s cubic-bezier(0.22,1,0.36,1);}',
            '.mya-entry:hover .mya-entry-media img{transform:scale(1.045);}',
            '.mya-entry-media::after{content:"";position:absolute;inset:auto 0 0 0;height:56%;background:linear-gradient(to top,rgba(24,25,26,0.55),transparent);}',
            '.mya-entry-count{position:absolute;top:10px;right:10px;z-index:2;background:rgba(24,25,26,0.62);backdrop-filter:blur(6px);color:#fff;border-radius:9999px;padding:3px 9px;font-size:10px;font-weight:700;}',
            '.mya-entry-body{padding:13px 14px 14px;}',
            '.mya-entry-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:7px;}',
            '.mya-entry-title{margin:0 0 5px;font-size:14px;font-weight:700;color:' + COLORS.onyx + ';letter-spacing:-0.01em;line-height:1.3;}',
            '.mya-entry-notes{margin:0;font-size:12px;color:#5b5b60;line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}',
            '.mya-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px;}',
            '.mya-chip{display:inline-flex;align-items:center;gap:4px;background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:9999px;padding:3px 9px;font-size:10.5px;font-weight:600;color:' + COLORS.blueberry + ';}',
            '.mya-date{font-size:10.5px;color:' + COLORS.muted + ';font-weight:600;white-space:nowrap;}',

            // Moisture meter
            '.mya-meter{display:inline-flex;gap:2.5px;align-items:center;}',
            '.mya-meter i{width:12px;height:4px;border-radius:2px;background:' + COLORS.stoneAlt + ';display:block;}',
            '.mya-meter i.on{background:' + COLORS.coralDark + ';}',

            // Entry detail overlay
            '.mya-detail{position:absolute;inset:0;z-index:45;background:rgba(24,25,26,0.55);backdrop-filter:blur(6px);display:flex;align-items:flex-end;animation:myaFadeIn 0.22s ease both;}',
            '.mya-detail-card{width:100%;max-height:92%;background:#ffffff;border-radius:26px 26px 0 0;overflow-y:auto;animation:myaSheetUp 0.4s cubic-bezier(0.22,1,0.36,1) both;}',
            '.mya-detail-hero{position:relative;width:100%;height:230px;background:' + COLORS.stoneAlt + ';}',
            '.mya-detail-hero img{width:100%;height:100%;object-fit:cover;display:block;}',
            '.mya-detail-x{position:absolute;top:12px;right:12px;width:32px;height:32px;border-radius:50%;border:none;background:rgba(255,255,255,0.92);color:' + COLORS.onyx + ';display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,0.14);transition:transform 0.2s ease;}',
            '.mya-detail-x:hover{transform:rotate(90deg);}',
            '.mya-detail-body{padding:16px 18px 22px;}',
            '.mya-detail-thumbs{display:flex;gap:7px;overflow-x:auto;margin-top:11px;scrollbar-width:none;}',
            '.mya-detail-thumbs::-webkit-scrollbar{display:none;}',
            '.mya-detail-thumbs img{width:58px;height:58px;border-radius:11px;object-fit:cover;flex-shrink:0;cursor:pointer;border:2px solid transparent;transition:border-color 0.2s ease,transform 0.2s ease;}',
            '.mya-detail-thumbs img:hover{transform:translateY(-2px);}',
            '.mya-detail-thumbs img.on{border-color:' + COLORS.coralDark + ';}',
            '.mya-kv{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:14px;}',
            '.mya-kv-cell{background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:13px;padding:9px 11px;}',
            '.mya-kv-k{font-size:9.5px;font-weight:700;color:' + COLORS.muted + ';text-transform:uppercase;letter-spacing:0.05em;}',
            '.mya-kv-v{font-size:13px;font-weight:700;color:' + COLORS.onyx + ';margin-top:2px;}',

            // Empty / guest states
            '.mya-blank{display:flex;flex-direction:column;align-items:center;text-align:center;padding:38px 26px;animation:myaRise 0.44s cubic-bezier(0.22,1,0.36,1) both;}',
            '.mya-blank-ico{width:60px;height:60px;border-radius:50%;background:' + COLORS.lightCoral + ';color:' + COLORS.coralDark + ';display:flex;align-items:center;justify-content:center;margin-bottom:13px;}',
            '.mya-blank h4{margin:0 0 6px;font-size:15.5px;font-weight:700;color:' + COLORS.onyx + ';letter-spacing:-0.01em;}',
            '.mya-blank p{margin:0 0 16px;font-size:12.5px;color:' + COLORS.muted + ';line-height:1.55;max-width:270px;}',

            // Profile sheet
            '.mya-pf-hero{display:flex;align-items:center;gap:13px;padding:2px 0 15px;border-bottom:1px solid ' + COLORS.border + ';}',
            '.mya-pf-ring{position:relative;width:60px;height:60px;flex-shrink:0;}',
            '.mya-pf-ring svg{position:absolute;inset:0;transform:rotate(-90deg);}',
            '.mya-pf-ring img{position:absolute;inset:5px;width:50px;height:50px;border-radius:50%;object-fit:cover;background:' + COLORS.stoneAlt + ';}',
            '.mya-pf-name{margin:0 0 3px;font-size:16.5px;font-weight:800;color:' + COLORS.onyx + ';letter-spacing:-0.02em;}',
            '.mya-pf-sub{font-size:11.5px;color:' + COLORS.muted + ';display:flex;align-items:center;gap:5px;flex-wrap:wrap;}',
            '.mya-pf-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;}',
            '.mya-pf-cell{background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:14px;padding:10px 11px;transition:transform 0.22s cubic-bezier(0.22,1,0.36,1),border-color 0.22s ease;}',
            '.mya-pf-cell:hover{transform:translateY(-2px);border-color:' + COLORS.borderHover + ';}',
            '.mya-pf-cell.empty{border-style:dashed;background:#ffffff;cursor:pointer;}',
            '.mya-pf-k{font-size:9.5px;font-weight:700;color:' + COLORS.muted + ';text-transform:uppercase;letter-spacing:0.05em;}',
            '.mya-pf-v{font-size:13.5px;font-weight:700;color:' + COLORS.onyx + ';margin-top:3px;letter-spacing:-0.01em;}',
            '.mya-pf-v.todo{color:' + COLORS.coralDark + ';font-weight:600;}',
            '.mya-pf-note{background:' + COLORS.sand + ';border-radius:14px;padding:11px 13px;font-size:12px;line-height:1.55;color:' + COLORS.onyxSoft + ';margin-top:10px;display:flex;gap:9px;align-items:flex-start;}',
            '.mya-goal{background:#ffffff;border:1px solid ' + COLORS.border + ';border-radius:15px;padding:12px 13px;margin-bottom:8px;transition:border-color 0.22s ease;}',
            '.mya-goal:hover{border-color:' + COLORS.borderHover + ';}',
            '.mya-goal-top{display:flex;align-items:baseline;justify-content:space-between;gap:9px;margin-bottom:8px;}',
            '.mya-goal-t{font-size:13px;font-weight:700;color:' + COLORS.onyx + ';letter-spacing:-0.01em;}',
            '.mya-goal-p{font-size:12.5px;font-weight:800;color:' + COLORS.coralDark + ';}',
            '.mya-bar{height:6px;border-radius:9999px;background:' + COLORS.stoneAlt + ';overflow:hidden;}',
            '.mya-bar span{display:block;height:100%;border-radius:9999px;background:linear-gradient(90deg,' + COLORS.coral + ',' + COLORS.coralDark + ');width:0;transition:width 0.9s cubic-bezier(0.22,1,0.36,1);}',
            '.mya-badges{display:flex;flex-wrap:wrap;gap:7px;}',
            '.mya-badge{display:inline-flex;align-items:center;gap:5px;border-radius:9999px;padding:5px 11px;font-size:11px;font-weight:700;background:' + COLORS.emeraldBg + ';color:' + COLORS.emerald + ';border:1px solid rgba(46,125,50,0.16);}',
            '.mya-badge.locked{background:' + COLORS.stone + ';color:' + COLORS.muted + ';border-color:' + COLORS.border + ';opacity:0.72;}',
            '.mya-act{width:100%;background:' + COLORS.stone + ';border:1px solid ' + COLORS.border + ';border-radius:13px;padding:11px 13px;margin-bottom:7px;display:flex;align-items:center;justify-content:space-between;gap:9px;font-size:12.5px;font-weight:600;color:' + COLORS.onyx + ';cursor:pointer;font-family:inherit;transition:background 0.2s ease,border-color 0.2s ease,transform 0.2s ease;}',
            '.mya-act:hover{background:' + COLORS.lightCoral + ';border-color:' + COLORS.coral + ';transform:translateX(3px);}',
            '.mya-act svg:last-child{color:' + COLORS.muted + ';flex-shrink:0;}',

            '@media (prefers-reduced-motion: reduce){',
            '  .mya-rise,.mya-blank,.mya-detail,.mya-detail-card,.mya-msg-row,.mya-qr,',
            '  .mya-history-item,.mya-status-indicator,.mya-jump-btn,.mya-launcher{animation:none!important;}',
            '  .mya-skel,.mya-status-dots i,.mya-caret,.mya-status-shimmer{animation:none!important;}',
            '  .mya-caret{opacity:1;}',
            '  .mya-entry,.mya-band-cell,.mya-pf-cell,.mya-act,.mya-reel-ring,.mya-entry-media img,',
            '  .mya-qr,.mya-history-item,.mya-send-btn,.mya-tool-btn,.mya-btn-icon,.mya-nav-item::after{transition:none!important;}',
            '}',

            // ---- Chrome polish ----
            // Quick reply pills
            '.mya-qr-row{display:flex;flex-wrap:wrap;gap:7px;margin:2px 0 14px;}',
            '.mya-qr{background:#ffffff;border:1px solid ' + COLORS.border + ';color:' + COLORS.onyx + ';border-radius:9999px;',
            'padding:7px 15px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;box-shadow:0 2px 6px rgba(34,35,35,0.04);',
            'animation:myaMsgIn 0.34s cubic-bezier(0.22,1,0.36,1) both;',
            'transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1),border-color 0.2s ease,box-shadow 0.2s ease,background 0.2s ease;}',
            '.mya-qr:hover{transform:translateY(-2px);border-color:' + COLORS.coral + ';background:' + COLORS.lightCoral + ';box-shadow:0 8px 18px rgba(231,166,144,0.26);}',
            '.mya-qr:active{transform:translateY(0) scale(0.97);}',

            // Jump-to-latest
            '.mya-jump-btn{position:sticky;bottom:10px;margin:0 auto;display:none;align-items:center;gap:6px;',
            'background:rgba(24,25,26,0.92);backdrop-filter:blur(8px);color:#ffffff;border:1px solid rgba(255,255,255,0.12);',
            'border-radius:9999px;padding:7px 15px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit;',
            'box-shadow:0 8px 22px rgba(0,0,0,0.24);z-index:5;animation:myaMsgIn 0.3s cubic-bezier(0.22,1,0.36,1) both;',
            'transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1),background 0.2s ease;}',
            '.mya-jump-btn:hover{transform:translateY(-2px);background:' + COLORS.onyx + ';}',

            // Nav strip: an animated underline rather than a hard swap
            '.mya-nav-item{position:relative;font-family:inherit;}',
            '.mya-nav-item::after{content:"";position:absolute;left:50%;bottom:3px;width:0;height:2px;border-radius:2px;',
            'background:' + COLORS.coralDark + ';transform:translateX(-50%);transition:width 0.28s cubic-bezier(0.22,1,0.36,1);}',
            '.mya-nav-item.active::after{width:18px;}',
            '.mya-nav-item:active{transform:scale(0.96);}',

            // Launcher: a slow breathing halo so it reads as present, not urgent
            '@keyframes myaHalo{0%,100%{box-shadow:0 10px 28px rgba(0,0,0,0.2),0 0 0 0 rgba(231,166,144,0.34);}50%{box-shadow:0 10px 28px rgba(0,0,0,0.2),0 0 0 9px rgba(231,166,144,0);}}',
            '.mya-launcher{animation:myaHalo 3.6s ease-out infinite;}',
            '.mya-launcher:hover{animation-play-state:paused;}',
            '.mya-launcher:active{transform:translateY(0) scale(0.97);}',

            // Composer: lift the whole bar on focus
            '.mya-composer-box{transition:box-shadow 0.24s ease;}',
            '.mya-composer-box:focus-within{box-shadow:0 -8px 22px rgba(34,35,35,0.05);}',
            '.mya-input-wrapper{transition:background 0.2s ease,border-color 0.2s ease,box-shadow 0.24s ease;}',
            '.mya-send-btn{transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1),background 0.2s ease;}',
            '.mya-send-btn:not(:disabled):active{transform:scale(0.92);}',
            '.mya-send-btn .mya-status-dots i{background:rgba(255,255,255,0.85);width:4px;height:4px;}',
            '.mya-tool-btn{transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1),background 0.16s ease,color 0.16s ease;}',
            '.mya-tool-btn:active{transform:scale(0.9);}',

            // Header icon buttons
            '.mya-btn-icon{transition:transform 0.22s cubic-bezier(0.34,1.56,0.64,1),background 0.16s ease,color 0.16s ease;}',
            '.mya-btn-icon:active{transform:scale(0.9);}',

            // Focus visibility for keyboard users, everywhere
            '.mya-nav-item:focus-visible,.mya-qr:focus-visible,.mya-history-item:focus-visible,',
            '.mya-entry:focus-visible,.mya-act:focus-visible,.mya-msg-tool:focus-visible,',
            '.mya-btn-icon:focus-visible,.mya-send-btn:focus-visible,.mya-tool-btn:focus-visible,',
            '.mya-reel-item:focus-visible,.mya-new-chat-btn:focus-visible{outline:2px solid ' + COLORS.coralDark + ';outline-offset:2px;}',

            // Mobile-First Responsive Breakpoint (< 640px)
            '@media (max-width: 640px) {',
            '  .mya-panel{position:fixed;inset:0;width:100vw;max-width:100vw;height:100vh;max-height:100vh;border-radius:0;border:none;left:0!important;right:0!important;bottom:0!important;}',
            '  .mya-btn-icon.mobile-back{display:flex;}',
            '  .mya-btn-icon.expand-btn{display:none;}',
            '  .mya-composer-box{padding-bottom:max(14px, env(safe-area-inset-bottom, 14px));}',
            '  .mya-launcher{bottom:16px;left:16px;}',
            '  .mya-profile-sheet-content{border-radius:24px 24px 0 0;}',
            '  .mya-band-num{font-size:18px;}',
            '  .mya-entry-media{height:200px;}',
            '  .mya-detail-card{max-height:96%;}',
            '}'
        ].join('\n');
        document.head.appendChild(style);
    }

    // ---- DOM Builder ----

    function buildWidget() {
        var side = state.position === 'bottom-right' ? 'is-right' : '';

        // Launcher Button
        var launcher = document.createElement('button');
        launcher.className = 'mya-launcher ' + side;
        launcher.setAttribute('aria-label', 'Open Mya Hair AI Companion');
        launcher.innerHTML = [
            getMyaLogoHtml(30, 'glow'),
            '<span>Mya</span>',
            '<span class="mya-launcher-badge">AI</span>'
        ].join('');
        launcher.addEventListener('click', togglePanel);

        // Main Panel
        var panel = document.createElement('div');
        panel.className = 'mya-panel ' + side;
        panel.style.display = 'none';

        // Header
        var header = document.createElement('div');
        header.className = 'mya-header';

        var headerLeft = document.createElement('div');
        headerLeft.className = 'mya-header-left';

        var backBtn = document.createElement('button');
        backBtn.className = 'mya-btn-icon mobile-back';
        backBtn.innerHTML = ICONS.arrowLeft;
        backBtn.setAttribute('aria-label', 'Back');
        backBtn.addEventListener('click', togglePanel);

        var titleBox = document.createElement('div');
        titleBox.className = 'mya-header-title';
        titleBox.innerHTML = getMyaLogoHtml(26) + '<span>Mya</span>';

        var contextPill = document.createElement('div');
        contextPill.className = 'mya-context-pill';
        contextPill.innerHTML = '<span class="mya-context-dot"></span><span class="mya-context-text">Hair Journey</span>';

        headerLeft.appendChild(backBtn);
        headerLeft.appendChild(titleBox);
        headerLeft.appendChild(contextPill);

        var headerActions = document.createElement('div');
        headerActions.className = 'mya-header-actions';

        var liveVoiceBtn = document.createElement('button');
        liveVoiceBtn.className = 'mya-btn-live-voice';
        liveVoiceBtn.innerHTML = ICONS.mic + '<span>Live Voice</span>';
        liveVoiceBtn.setAttribute('title', 'Start Gemini Live Voice Session');
        liveVoiceBtn.addEventListener('click', startLiveVoice);

        var expandBtn = document.createElement('button');
        expandBtn.className = 'mya-btn-icon expand-btn';
        expandBtn.innerHTML = ICONS.expand;
        expandBtn.setAttribute('title', 'Expand workspace');
        expandBtn.addEventListener('click', toggleExpand);

        var closeBtn = document.createElement('button');
        closeBtn.className = 'mya-btn-icon';
        closeBtn.innerHTML = ICONS.close;
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.addEventListener('click', togglePanel);

        headerActions.appendChild(liveVoiceBtn);
        headerActions.appendChild(expandBtn);
        headerActions.appendChild(closeBtn);

        header.appendChild(headerLeft);
        header.appendChild(headerActions);

        // Multi-View Navigation Strip
        var navStrip = document.createElement('nav');
        navStrip.className = 'mya-nav-strip';
        navStrip.innerHTML = [
            '<button class="mya-nav-item active" data-view="chat">' + ICONS.chat + '<span>Chat</span></button>',
            '<button class="mya-nav-item" data-view="stories">' + ICONS.stories + '<span>Stories</span><span class="mya-nav-dot"></span></button>',
            '<button class="mya-nav-item" data-view="history">' + ICONS.history + '<span>History</span></button>',
            '<button class="mya-nav-item" data-view="profile">' + ICONS.profile + '<span>Profile</span></button>'
        ].join('');

        navStrip.addEventListener('click', function (e) {
            var btn = e.target.closest('.mya-nav-item');
            if (!btn) return;
            var view = btn.getAttribute('data-view');
            switchView(view);
        });

        // Views Container
        var viewsContainer = document.createElement('div');
        viewsContainer.className = 'mya-view-container';

        // 1. Chat View
        var chatView = document.createElement('div');
        chatView.className = 'mya-view active';
        chatView.setAttribute('data-view-pane', 'chat');

        var stream = document.createElement('div');
        stream.className = 'mya-stream';
        stream.addEventListener('scroll', function () {
            state.els.atBottom = isNearBottom(stream);
            state.els.jumpBtn.style.display = state.els.atBottom ? 'none' : 'flex';
        });

        var jumpBtn = document.createElement('button');
        jumpBtn.className = 'mya-jump-btn';
        jumpBtn.type = 'button';
        jumpBtn.setAttribute('aria-label', 'Jump to the latest message');
        jumpBtn.innerHTML = ICONS.chevronDown + '<span>Latest</span>';
        jumpBtn.addEventListener('click', function () {
            scrollToBottom(stream);
            jumpBtn.style.display = 'none';
        });

        chatView.appendChild(stream);
        chatView.appendChild(jumpBtn);

        // 2. Journey Stories View
        var storiesView = document.createElement('div');
        storiesView.className = 'mya-view mya-stories-view';
        storiesView.setAttribute('data-view-pane', 'stories');

        // 3. Conversation History View
        var historyView = document.createElement('div');
        historyView.className = 'mya-view mya-history-view';
        historyView.setAttribute('data-view-pane', 'history');

        viewsContainer.appendChild(chatView);
        viewsContainer.appendChild(storiesView);
        viewsContainer.appendChild(historyView);

        // Composer Box (Active in Chat View)
        var composerBox = document.createElement('div');
        composerBox.className = 'mya-composer-box';

        var photoStage = document.createElement('div');
        photoStage.className = 'mya-photo-stage';
        photoStage.style.display = 'none';

        var cameraInput = document.createElement('input');
        cameraInput.type = 'file';
        cameraInput.accept = 'image/*';
        cameraInput.capture = 'environment';
        cameraInput.style.display = 'none';
        cameraInput.addEventListener('change', handleFileInput);

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.style.display = 'none';
        fileInput.addEventListener('change', handleFileInput);

        var drawer = document.createElement('div');
        drawer.className = 'mya-drawer';
        drawer.innerHTML = [
            '<div class="mya-drawer-item" data-action="camera">' + ICONS.camera + '<span>Take journey photo</span></div>',
            '<div class="mya-drawer-item" data-action="goal">' + ICONS.goal + '<span>Review active goals</span></div>',
            '<div class="mya-drawer-item" data-action="routine">' + ICONS.routine + '<span>Show today\'s routine</span></div>',
            '<div class="mya-drawer-item" data-action="product">' + ICONS.leaf + '<span>Check product match</span></div>',
            '<div class="mya-drawer-item" data-action="weather">' + ICONS.weather + '<span>Today\'s hair weather</span></div>'
        ].join('');

        drawer.addEventListener('click', function (e) {
            var item = e.target.closest('.mya-drawer-item');
            if (!item) return;
            var action = item.getAttribute('data-action');
            toggleDrawer(false);
            if (action === 'camera') cameraInput.click();
            else if (action === 'upload') fileInput.click();
            else if (action === 'goal') sendMessage('What are my current hair goals?');
            else if (action === 'routine') sendMessage('Show today\'s routine checklist');
            else if (action === 'product') sendMessage('Does my current product regimen match my hair porosity and texture?');
            else if (action === 'weather') sendMessage('What is the hair weather forecast and humidity recommendation for my hair today?');
            else if (action === 'journal') sendMessage('I would like to record a hair journey journal entry');
        });

        var inputWrapper = document.createElement('div');
        inputWrapper.className = 'mya-input-wrapper';

        var textarea = document.createElement('textarea');
        textarea.className = 'mya-textarea';
        textarea.rows = 1;
        textarea.placeholder = 'Ask Mya about your hair journey...';

        textarea.addEventListener('input', function () {
            autosizeComposer();
            sendBtn.disabled = this.value.trim().length === 0 && !state.stagedPhoto;
        });

        // Collapse on blur when there's nothing to keep it open for, so an
        // abandoned draft doesn't leave the composer tall.
        textarea.addEventListener('blur', function () {
            if (!this.value.trim()) autosizeComposer();
        });

        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                submitComposer();
            }
        });

        var actionBar = document.createElement('div');
        actionBar.className = 'mya-action-bar';

        var leftGroup = document.createElement('div');
        leftGroup.className = 'mya-action-group-left';

        var plusBtn = document.createElement('button');
        plusBtn.className = 'mya-tool-btn';
        plusBtn.innerHTML = ICONS.plus;
        plusBtn.setAttribute('title', 'Add to conversation');
        plusBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleDrawer();
        });

        var quickCameraBtn = document.createElement('button');
        quickCameraBtn.className = 'mya-tool-btn';
        quickCameraBtn.innerHTML = ICONS.camera;
        quickCameraBtn.setAttribute('title', 'Document journey photo');
        quickCameraBtn.addEventListener('click', function () {
            cameraInput.click();
        });

        var quickClipBtn = document.createElement('button');
        quickClipBtn.className = 'mya-tool-btn';
        quickClipBtn.innerHTML = ICONS.paperclip;
        quickClipBtn.setAttribute('title', 'Upload image');
        quickClipBtn.addEventListener('click', function () {
            fileInput.click();
        });

        leftGroup.appendChild(plusBtn);
        leftGroup.appendChild(quickCameraBtn);
        leftGroup.appendChild(quickClipBtn);

        var rightGroup = document.createElement('div');
        rightGroup.className = 'mya-action-group-right';

        var audioWave = document.createElement('div');
        audioWave.className = 'mya-audio-wave';
        audioWave.innerHTML = '<div class="mya-audio-bar"></div><div class="mya-audio-bar"></div><div class="mya-audio-bar"></div>';

        var micBtn = document.createElement('button');
        micBtn.className = 'mya-tool-btn';
        micBtn.innerHTML = ICONS.mic;
        micBtn.setAttribute('title', 'Voice dictation');
        micBtn.addEventListener('click', toggleVoiceInput);

        var sendBtn = document.createElement('button');
        sendBtn.className = 'mya-send-btn';
        sendBtn.innerHTML = ICONS.send;
        sendBtn.disabled = true;
        sendBtn.setAttribute('aria-label', 'Send message');
        sendBtn.addEventListener('click', submitComposer);

        rightGroup.appendChild(audioWave);
        rightGroup.appendChild(micBtn);
        rightGroup.appendChild(sendBtn);

        // actionBar is retained only so nothing downstream that queries it
        // breaks; the groups are parented directly to the wrapper below.

        // Single row. Stacking the textarea above a full-width button bar cost
        // ~82px of vertical space at rest no matter how short the input was;
        // inline it is ~44px, and the difference goes to the conversation.
        inputWrapper.appendChild(leftGroup);
        inputWrapper.appendChild(textarea);
        inputWrapper.appendChild(rightGroup);

        composerBox.appendChild(photoStage);
        composerBox.appendChild(drawer);
        composerBox.appendChild(inputWrapper);
        composerBox.appendChild(cameraInput);
        composerBox.appendChild(fileInput);

        // Profile Bottom Sheet / Modal
        var profileSheet = buildProfileSheet();

        // Gemini Live Voice Overlay
        var liveOverlay = buildLiveOverlay();

        panel.appendChild(header);
        panel.appendChild(navStrip);
        panel.appendChild(viewsContainer);
        panel.appendChild(composerBox);
        panel.appendChild(profileSheet);
        panel.appendChild(liveOverlay);

        document.body.appendChild(launcher);
        document.body.appendChild(panel);

        state.els = {
            launcher: launcher,
            panel: panel,
            header: header,
            navStrip: navStrip,
            contextText: contextPill.querySelector('.mya-context-text'),
            expandBtn: expandBtn,
            viewsContainer: viewsContainer,
            stream: stream,
            storiesView: storiesView,
            historyView: historyView,
            profileSheet: profileSheet,
            jumpBtn: jumpBtn,
            composerBox: composerBox,
            textarea: textarea,
            sendBtn: sendBtn,
            drawer: drawer,
            photoStage: photoStage,
            micBtn: micBtn,
            audioWave: audioWave,
            liveOverlay: liveOverlay,
            liveTimer: liveOverlay.querySelector('.mya-live-timer'),
            liveDot: liveOverlay.querySelector('.mya-live-dot'),
            liveCanvas: liveOverlay.querySelector('.mya-live-canvas'),
            liveStateBanner: liveOverlay.querySelector('.mya-live-state-banner'),
            liveTickerRole: liveOverlay.querySelector('.mya-live-ticker-role'),
            liveTickerText: liveOverlay.querySelector('.mya-live-ticker-text'),
            liveMuteBtn: liveOverlay.querySelector('.mya-live-btn-icon-only'),
            atBottom: true
        };

        // Render initial views
        applyLocalPlatformNav();
        renderStoriesView();
        loadConversationHistory();
        updateContextUI();
        refreshJourney();
    }

    // ---- Multi-View Switcher ----

    function switchView(viewName) {
        closeEntryDetail();
        // Fallback to chat if a view is disabled by capabilities or missing local platform
        if (viewName === 'stories' && (!state.capabilities.stories || (!hasLocalPlatform() && state.platform !== 'wordpress'))) {
            viewName = 'chat';
        }
        if (viewName === 'profile' && (!state.capabilities.profile || (!hasLocalPlatform() && state.platform !== 'wordpress'))) {
            viewName = 'chat';
        }
        if (viewName === 'history' && !state.capabilities.history) {
            viewName = 'chat';
        }
        state.activeView = viewName;

        // Update nav strip active state
        var navItems = state.els.navStrip.querySelectorAll('.mya-nav-item');
        navItems.forEach(function (item) {
            item.classList.toggle('active', item.getAttribute('data-view') === viewName);
        });

        // If user tapped Profile, open profile sheet modal directly
        if (viewName === 'profile') {
            openProfileSheet();
            return;
        }

        // Toggle views
        var panes = state.els.viewsContainer.querySelectorAll('.mya-view');
        panes.forEach(function (pane) {
            pane.classList.toggle('active', pane.getAttribute('data-view-pane') === viewName);
        });

        // Composer is only visible in Chat view
        state.els.composerBox.style.display = viewName === 'chat' ? 'block' : 'none';

        if (viewName === 'chat') {
            if (state.els.stream.children.length === 0) {
                renderEmptyState();
            }
            scrollToBottom(state.els.stream);
        } else if (viewName === 'stories') {
            renderStoriesView();
        } else if (viewName === 'history') {
            loadConversationHistory();
        }
    }

    // ---- Local Platform Bridge ----
    //
    // Chat, live voice and history are global MYAVANA capabilities and work on
    // any surface. Stories and Profile are *local* features: they render the
    // host platform's own records (WordPress journal entries, goals, routines,
    // strand profile) and only exist when that platform registers a provider.
    // The widget never invents journey data — a host that supplies nothing
    // simply doesn't get those tabs.

    function registerLocalPlatform(provider) {
        if (!provider || typeof provider.getJourney !== 'function') {
            console.warn('[Mya] registerLocalPlatform requires { getJourney() }');
            return;
        }
        state.localPlatform = provider;
        state.platform = provider.platform || 'wordpress';
        // Auto-enable local platform capabilities when provider registers
        state.capabilities.stories = true;
        state.capabilities.profile = true;
        state.capabilities.photoJourney = true;
        if (provider.capabilities) {
            Object.assign(state.capabilities, provider.capabilities);
        }
        if (state.els.navStrip) {
            applyLocalPlatformNav();
            refreshJourney();
        }
    }

    function hasLocalPlatform() {
        return !!(state.localPlatform && typeof state.localPlatform.getJourney === 'function');
    }

    // Stories/Profile tabs are hidden until a host platform registers or local capabilities are enabled.
    function applyLocalPlatformNav() {
        if (!state.els.navStrip) return;
        var onStories = (hasLocalPlatform() || (state.platform === 'wordpress' && state.capabilities.stories));
        var onProfile = (hasLocalPlatform() || (state.platform === 'wordpress' && state.capabilities.profile));
        var onHistory = state.capabilities.history !== false;

        var storiesBtn = state.els.navStrip.querySelector('.mya-nav-item[data-view="stories"]');
        if (storiesBtn) storiesBtn.style.display = onStories ? '' : 'none';

        var profileBtn = state.els.navStrip.querySelector('.mya-nav-item[data-view="profile"]');
        if (profileBtn) profileBtn.style.display = onProfile ? '' : 'none';

        var historyBtn = state.els.navStrip.querySelector('.mya-nav-item[data-view="history"]');
        if (historyBtn) historyBtn.style.display = onHistory ? '' : 'none';

        if (!onStories && state.activeView === 'stories') {
            switchView('chat');
        }
        if (!onProfile && state.activeView === 'profile') {
            switchView('chat');
        }
    }

    /**
     * Pull the host's journey record. Resolves to the cached copy unless
     * `force` is set, so switching tabs is instant after the first load.
     */
    function refreshJourney(force) {
        if (!hasLocalPlatform()) return Promise.resolve(null);
        if (state.journeyData && !force) return Promise.resolve(state.journeyData);
        if (state.journeyLoading) return Promise.resolve(null);

        state.journeyLoading = true;
        state.journeyError = false;

        return Promise.resolve()
            .then(function () { return state.localPlatform.getJourney(); })
            .then(function (data) {
                state.journeyLoading = false;
                state.journeyData = data || {};
                var stats = state.journeyData.stats || {};
                if (typeof stats.currentStreak === 'number') state.streak = stats.currentStreak;
                if (state.activeView === 'stories') renderStoriesView();
                if (state.els.profileSheet && state.els.profileSheet.style.display === 'flex') {
                    renderProfileSheetContent();
                }
                return state.journeyData;
            })
            .catch(function (err) {
                console.warn('[Mya] Local journey unavailable:', err && err.message);
                state.journeyLoading = false;
                state.journeyError = true;
                if (state.activeView === 'stories') renderStoriesView();
                return null;
            });
    }

    // ---- View 2: Hair Journey Stories (local: real WordPress entries) ----

    var ENTRY_TYPES = {
        wash_day: 'Wash Day',
        length_check: 'Length Check',
        milestone: 'Milestone',
        quick_checkin: 'Check-in',
        treatment: 'Treatment',
        protective_style: 'Protective Style',
        trim: 'Trim',
        product_trial: 'Product Trial'
    };

    function entryTypeLabel(entry) {
        var t = (entry && entry.entryType) || 'quick_checkin';
        return ENTRY_TYPES[t] || t.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function entryDate(entry) {
        var d = new Date((entry && entry.date || '').replace(' ', 'T'));
        return isNaN(d.getTime()) ? null : d;
    }

    function formatEntryDate(entry) {
        var d = entryDate(entry);
        if (!d) return entry && entry.date ? String(entry.date) : '';
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function monthKey(entry) {
        var d = entryDate(entry);
        if (!d) return 'Earlier';
        return d.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    }

    function entryPhotos(entry) {
        var out = [];
        if (entry && Array.isArray(entry.photos)) out = entry.photos.filter(Boolean);
        if (entry && entry.featuredImage && out.indexOf(entry.featuredImage) === -1) out.unshift(entry.featuredImage);
        return out;
    }

    function moistureMeterHtml(level) {
        var n = Math.max(0, Math.min(5, parseInt(level, 10) || 0));
        if (!n) return '';
        var bars = '';
        for (var i = 1; i <= 5; i++) bars += '<i class="' + (i <= n ? 'on' : '') + '"></i>';
        return '<span class="mya-meter" title="Moisture ' + n + ' of 5" aria-label="Moisture ' + n + ' of 5">' + bars + '</span>';
    }

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function el(tag, className, html) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (html != null) node.innerHTML = html;
        return node;
    }

    function renderStoriesSkeleton(container) {
        var wrap = el('div', 'mya-section');
        var reel = '<div style="display:flex;gap:13px;padding:14px 0 4px;">' +
            new Array(4).join('x').split('x').map(function () {
                return '<div style="display:flex;flex-direction:column;align-items:center;gap:6px;">' +
                    '<div class="mya-skel" style="width:62px;height:62px;border-radius:50%;"></div>' +
                    '<div class="mya-skel" style="width:46px;height:8px;"></div></div>';
            }).join('') + '</div>';
        var cards = new Array(3).join('x').split('x').map(function () {
            return '<div class="mya-skel" style="height:150px;border-radius:18px;margin-bottom:11px;"></div>';
        }).join('');
        wrap.innerHTML = reel + '<div class="mya-skel" style="width:120px;height:10px;margin:14px 0 12px;"></div>' + cards;
        container.appendChild(wrap);
    }

    function renderStoriesView() {
        var container = state.els.storiesView;
        if (!container) return;
        container.innerHTML = '';

        if (!hasLocalPlatform()) {
            container.appendChild(blankState(
                ICONS.journal,
                'Stories live in your Hair Journey',
                'Open Mya from myhairjourney.ai to browse your photo timeline, length checks and milestones.'
            ));
            return;
        }

        if (state.journeyLoading || (!state.journeyData && !state.journeyError)) {
            renderStoriesSkeleton(container);
            if (!state.journeyData) refreshJourney();
            return;
        }

        if (state.journeyError) {
            var errState = blankState(
                ICONS.info,
                "Couldn't reach your journey",
                'Your entries are safe — the connection just dropped. Try again in a moment.',
                'Retry',
                function () { state.journeyError = false; renderStoriesView(); refreshJourney(true); }
            );
            container.appendChild(errState);
            return;
        }

        var data = state.journeyData || {};
        var entries = (data.entries || []).slice();
        var stats = data.stats || {};

        if (entries.length === 0) {
            container.appendChild(blankState(
                ICONS.camera,
                'Your journey starts with one entry',
                'Log a wash day, a length check or a quick note. Every entry you add here is what Mya reads when she answers you.',
                'Log my first entry',
                function () { openLocalComposer(); }
            ));
            return;
        }

        // Newest first, tolerant of hosts that return either order.
        entries.sort(function (a, b) {
            var da = entryDate(a), db = entryDate(b);
            return (db ? db.getTime() : 0) - (da ? da.getTime() : 0);
        });

        // --- Aggregate band: only numbers the platform actually reported ---
        var band = el('div', 'mya-section');
        var cells = [];
        cells.push({ num: data.totalEntries != null ? data.totalEntries : entries.length, lbl: 'Entries' });
        if (typeof stats.currentStreak === 'number') {
            cells.push({ num: stats.currentStreak, lbl: 'Day Streak' });
        }
        if (data.lengthGain != null) {
            cells.push({ num: (data.lengthGain > 0 ? '+' : '') + data.lengthGain, unit: 'in', lbl: 'Length Gain' });
        } else if (data.dayCount) {
            cells.push({ num: data.dayCount, lbl: 'Days In' });
        }
        var photoTotal = entries.reduce(function (n, e) { return n + entryPhotos(e).length; }, 0);
        if (cells.length < 3) cells.push({ num: photoTotal, lbl: 'Photos' });

        band.innerHTML = '<div class="mya-band">' + cells.slice(0, 3).map(function (c, idx) {
            return '<div class="mya-band-cell mya-rise" style="animation-delay:' + (idx * 60) + 'ms">' +
                '<div class="mya-band-num">' + esc(c.num) + (c.unit ? '<small>' + esc(c.unit) + '</small>' : '') + '</div>' +
                '<div class="mya-band-lbl">' + esc(c.lbl) + '</div></div>';
        }).join('') + '</div>';
        container.appendChild(band);

        // --- Photo reel: only entries that genuinely have a photo ---
        var photoEntries = entries.filter(function (e) { return entryPhotos(e).length > 0; });

        var reelTitle = el('div', 'mya-section');
        reelTitle.innerHTML = '<div class="mya-section-title">' + ICONS.image +
            '<span>Recent Looks</span><span class="mya-rule"></span></div>';
        container.appendChild(reelTitle);

        var reel = el('div', 'mya-reel');

        var addBtn = el('button', 'mya-reel-item');
        addBtn.type = 'button';
        addBtn.setAttribute('aria-label', 'Add a new journey entry');
        addBtn.innerHTML = '<span class="mya-reel-add">' + ICONS.plus + '</span>' +
            '<span class="mya-reel-lbl">Add</span>';
        addBtn.addEventListener('click', openLocalComposer);
        reel.appendChild(addBtn);

        if (photoEntries.length === 0) {
            var hint = el('div', '', '<div style="font-size:11.5px;color:' + COLORS.muted +
                ';align-self:center;line-height:1.5;max-width:190px;">No photos yet — add one and it will appear here.</div>');
            reel.appendChild(hint);
        }

        photoEntries.slice(0, 12).forEach(function (entry, idx) {
            var item = el('button', 'mya-reel-item mya-rise');
            item.type = 'button';
            item.style.animationDelay = (idx * 45) + 'ms';
            item.setAttribute('aria-label', entry.title + ', ' + formatEntryDate(entry));
            item.innerHTML =
                '<span class="mya-reel-ring"><img class="mya-reel-img" loading="lazy" src="' + esc(entryPhotos(entry)[0]) +
                '" alt="' + esc(entry.title) + '" /></span>' +
                '<span class="mya-reel-lbl">' + esc(entryTypeLabel(entry)) + '</span>' +
                '<span class="mya-reel-date">' + esc(shortDate(entry)) + '</span>';
            item.addEventListener('click', function () { openEntryDetail(entry); });
            reel.appendChild(item);
        });
        container.appendChild(reel);

        // --- Timeline grouped by real month ---
        var feed = el('div', 'mya-section');
        var currentMonth = null;
        entries.forEach(function (entry, idx) {
            var mk = monthKey(entry);
            if (mk !== currentMonth) {
                currentMonth = mk;
                feed.appendChild(el('div', 'mya-month', esc(mk)));
            }
            feed.appendChild(buildEntryCard(entry, idx));
        });
        container.appendChild(feed);

        var footer = el('div', 'mya-section');
        footer.style.padding = '4px 16px 22px';
        var allBtn = el('button', 'mya-act');
        allBtn.type = 'button';
        allBtn.innerHTML = '<span style="display:flex;align-items:center;gap:8px;">' + ICONS.journal +
            '<span>Open full journey timeline</span></span>' + ICONS.chevronRight;
        allBtn.addEventListener('click', function () { navigateLocal('journey'); });
        footer.appendChild(allBtn);
        container.appendChild(footer);
    }

    function shortDate(entry) {
        var d = entryDate(entry);
        if (!d) return '';
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function buildEntryCard(entry, idx) {
        var card = el('button', 'mya-entry mya-rise');
        card.type = 'button';
        card.style.animationDelay = Math.min(idx * 40, 320) + 'ms';

        var photos = entryPhotos(entry);
        var html = '';

        if (photos.length) {
            html += '<div class="mya-entry-media">' +
                (photos.length > 1 ? '<span class="mya-entry-count">' + photos.length + ' photos</span>' : '') +
                '<img loading="lazy" src="' + esc(photos[0]) + '" alt="' + esc(entry.title) + '" /></div>';
        }

        html += '<div class="mya-entry-body">' +
            '<div class="mya-entry-head">' +
            '<span class="mya-pill">' + esc(entryTypeLabel(entry)) + '</span>' +
            '<span class="mya-date">' + esc(formatEntryDate(entry)) + '</span>' +
            '</div>' +
            '<h4 class="mya-entry-title">' + esc(entry.title || entryTypeLabel(entry)) + '</h4>';

        if (entry.notes) {
            html += '<p class="mya-entry-notes">' + esc(entry.notes) + '</p>';
        }

        var chips = [];
        if (entry.moistureLevel) chips.push('<span class="mya-chip">Moisture ' + moistureMeterHtml(entry.moistureLevel) + '</span>');
        if (entry.mood) chips.push('<span class="mya-chip">' + esc(entry.mood) + '</span>');
        if (entry.hairLength) {
            chips.push('<span class="mya-chip">' + esc(entry.hairLength) + '&quot;' +
                (entry.hairLengthPoint ? ' &middot; ' + esc(entry.hairLengthPoint) : '') + '</span>');
        }
        if (entry.scalpState) chips.push('<span class="mya-chip">Scalp: ' + esc(entry.scalpState) + '</span>');
        (entry.productsUsed || []).slice(0, 2).forEach(function (prod) {
            chips.push('<span class="mya-chip">' + ICONS.leaf + esc(typeof prod === 'string' ? prod : (prod.name || '')) + '</span>');
        });
        if (chips.length) html += '<div class="mya-chips">' + chips.join('') + '</div>';

        html += '</div>';
        card.innerHTML = html;
        card.addEventListener('click', function () { openEntryDetail(entry); });
        return card;
    }

    // ---- Entry detail overlay ----

    function openEntryDetail(entry) {
        closeEntryDetail();

        var photos = entryPhotos(entry);
        var overlay = el('div', 'mya-detail');
        var card = el('div', 'mya-detail-card');

        var hero = photos.length
            ? '<div class="mya-detail-hero"><img src="' + esc(photos[0]) + '" alt="' + esc(entry.title) + '" />' +
              '<button class="mya-detail-x" type="button" aria-label="Close entry">' + ICONS.close + '</button></div>'
            : '<div style="position:relative;padding:18px 18px 0;"><button class="mya-detail-x" type="button" style="position:static;float:right;" aria-label="Close entry">' + ICONS.close + '</button></div>';

        var kv = [];
        if (entry.moistureLevel) kv.push(['Moisture', moistureMeterHtml(entry.moistureLevel) + ' <span style="margin-left:5px;">' + entry.moistureLevel + '/5</span>']);
        if (entry.mood) kv.push(['Mood', esc(entry.mood)]);
        if (entry.scalpState) kv.push(['Scalp', esc(entry.scalpState)]);
        if (entry.hairLength) kv.push(['Length', esc(entry.hairLength) + '&quot;' + (entry.hairLengthPoint ? ' <span style="font-weight:600;color:' + COLORS.muted + ';">' + esc(entry.hairLengthPoint) + '</span>' : '')]);

        var body = '<div class="mya-detail-body">' +
            '<div class="mya-entry-head"><span class="mya-pill">' + esc(entryTypeLabel(entry)) + '</span>' +
            '<span class="mya-date">' + esc(formatEntryDate(entry)) + '</span></div>' +
            '<h3 style="margin:8px 0 0;font-size:18px;font-weight:800;color:' + COLORS.onyx + ';letter-spacing:-0.02em;line-height:1.28;">' +
            esc(entry.title || entryTypeLabel(entry)) + '</h3>';

        if (photos.length > 1) {
            body += '<div class="mya-detail-thumbs">' + photos.map(function (src, i) {
                return '<img src="' + esc(src) + '" class="' + (i === 0 ? 'on' : '') + '" alt="Photo ' + (i + 1) + '" />';
            }).join('') + '</div>';
        }

        if (entry.notes) {
            body += '<p style="margin:12px 0 0;font-size:13px;line-height:1.62;color:#4a4a4f;white-space:pre-wrap;">' + esc(entry.notes) + '</p>';
        }

        if (kv.length) {
            body += '<div class="mya-kv">' + kv.map(function (pair) {
                return '<div class="mya-kv-cell"><div class="mya-kv-k">' + pair[0] + '</div><div class="mya-kv-v">' + pair[1] + '</div></div>';
            }).join('') + '</div>';
        }

        if ((entry.productsUsed || []).length) {
            body += '<div class="mya-section-title" style="padding:0;">' + ICONS.leaf + '<span>Products Used</span><span class="mya-rule"></span></div>' +
                '<div class="mya-chips" style="margin-top:0;">' + entry.productsUsed.map(function (prod) {
                    return '<span class="mya-chip">' + esc(typeof prod === 'string' ? prod : (prod.name || '')) + '</span>';
                }).join('') + '</div>';
        }

        body += '<button class="mya-action-btn primary mya-detail-ask" type="button" style="width:100%;justify-content:center;margin-top:16px;padding:12px;">' +
            ICONS.chat + '<span>Ask Mya about this entry</span></button>' +
            '<button class="mya-act mya-detail-open" type="button" style="margin-top:8px;">' +
            '<span style="display:flex;align-items:center;gap:8px;">' + ICONS.journal + '<span>Open in Hair Journey</span></span>' +
            ICONS.chevronRight + '</button></div>';

        card.innerHTML = hero + body;
        overlay.appendChild(card);
        state.els.panel.appendChild(overlay);
        state.els.entryDetail = overlay;

        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeEntryDetail(); });
        card.querySelector('.mya-detail-x').addEventListener('click', closeEntryDetail);

        // Thumbnail gallery swaps the hero image in place.
        var heroImg = card.querySelector('.mya-detail-hero img');
        card.querySelectorAll('.mya-detail-thumbs img').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                if (heroImg) heroImg.src = thumb.src;
                card.querySelectorAll('.mya-detail-thumbs img').forEach(function (t) { t.classList.remove('on'); });
                thumb.classList.add('on');
            });
        });

        card.querySelector('.mya-detail-ask').addEventListener('click', function () {
            closeEntryDetail();
            switchView('chat');
            sendMessage(buildEntryPrompt(entry));
        });
        card.querySelector('.mya-detail-open').addEventListener('click', function () {
            closeEntryDetail();
            navigateLocal('journey', { entryId: entry.id });
        });
    }

    function closeEntryDetail() {
        if (state.els.entryDetail) {
            state.els.entryDetail.remove();
            state.els.entryDetail = null;
        }
    }

    /**
     * A prompt built from the entry's real recorded fields, so Mya answers
     * against what the member actually logged rather than a generic date.
     */
    function buildEntryPrompt(entry) {
        var bits = ['My ' + entryTypeLabel(entry).toLowerCase() + ' entry "' + (entry.title || '') + '" from ' + formatEntryDate(entry)];
        if (entry.moistureLevel) bits.push('moisture ' + entry.moistureLevel + '/5');
        if (entry.mood) bits.push('mood: ' + entry.mood);
        if (entry.scalpState) bits.push('scalp: ' + entry.scalpState);
        if (entry.hairLength) bits.push('length ' + entry.hairLength + ' inches' + (entry.hairLengthPoint ? ' at the ' + entry.hairLengthPoint : ''));
        if ((entry.productsUsed || []).length) {
            bits.push('products: ' + entry.productsUsed.map(function (p) {
                return typeof p === 'string' ? p : (p.name || '');
            }).filter(Boolean).join(', '));
        }
        return bits.join(' — ') + '. What does this tell you about my hair, and what should I do next?';
    }

    // ---- Local platform helpers ----

    function openLocalComposer() {
        if (state.localPlatform && typeof state.localPlatform.openEntryComposer === 'function') {
            state.localPlatform.openEntryComposer();
            togglePanel();
            return;
        }
        // No host composer: fall back to staging a photo in the chat.
        switchView('chat');
        var input = state.els.panel.querySelector('.mya-composer-box input[type="file"]');
        if (input) input.click();
    }

    function navigateLocal(tab, params) {
        if (state.localPlatform && typeof state.localPlatform.navigate === 'function') {
            state.localPlatform.navigate(tab, params || {});
            togglePanel();
        }
    }

    function blankState(icon, title, body, ctaLabel, onCta) {
        var wrap = el('div', 'mya-blank');
        wrap.innerHTML = '<div class="mya-blank-ico">' + icon + '</div>' +
            '<h4>' + esc(title) + '</h4><p>' + esc(body) + '</p>';
        if (ctaLabel) {
            var btn = el('button', 'mya-action-btn primary');
            btn.type = 'button';
            btn.style.cssText = 'justify-content:center;padding:11px 20px;';
            btn.innerHTML = ICONS.sparkles + '<span>' + esc(ctaLabel) + '</span>';
            btn.addEventListener('click', onCta);
            wrap.appendChild(btn);
        }
        return wrap;
    }

    // ---- View 3: Conversation History ----

    function loadConversationHistory() {
        var container = state.els.historyView;
        if (!container) return;
        container.innerHTML = '';

        var toolbar = el('div', 'mya-history-toolbar');
        var newChatBtn = el('button', 'mya-new-chat-btn', ICONS.plusCircle + '<span>New Conversation</span>');
        newChatBtn.type = 'button';
        newChatBtn.addEventListener('click', startNewConversation);
        toolbar.appendChild(newChatBtn);
        container.appendChild(toolbar);

        var searchWrap = el('div', 'mya-search-wrap',
            ICONS.search +
            '<input type="text" class="mya-history-search" placeholder="Search conversations..." aria-label="Search conversations" />' +
            '<button type="button" class="mya-search-clear" aria-label="Clear search">' + ICONS.close + '</button>');
        container.appendChild(searchWrap);

        var searchInput = searchWrap.querySelector('input');
        var clearBtn = searchWrap.querySelector('.mya-search-clear');

        function applyFilter() {
            var q = searchInput.value.trim().toLowerCase();
            clearBtn.classList.toggle('on', q.length > 0);

            var anyVisible = false;
            container.querySelectorAll('.mya-history-item').forEach(function (item) {
                var hit = item.getAttribute('data-search').indexOf(q) !== -1;
                item.style.display = hit ? 'flex' : 'none';
                if (hit) anyVisible = true;
            });

            // Hide a group heading once every row beneath it is filtered out.
            container.querySelectorAll('.mya-history-group').forEach(function (group) {
                var visible = group.querySelectorAll('.mya-history-item:not([style*="display: none"])').length;
                group.style.display = visible ? '' : 'none';
            });

            var noHits = container.querySelector('.mya-history-nohits');
            if (noHits) noHits.style.display = (q && !anyVisible) ? 'flex' : 'none';
        }

        searchInput.addEventListener('input', applyFilter);
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            applyFilter();
            searchInput.focus();
        });

        // Skeleton rows while the list loads, so the panel never flashes empty.
        var skeleton = el('div', 'mya-history-skeleton');
        skeleton.innerHTML = '<div class="mya-skel" style="width:90px;height:9px;margin:18px 0 10px;"></div>' +
            '<div class="mya-skel" style="height:59px;border-radius:15px;margin-bottom:7px;"></div>'.repeat(3);
        container.appendChild(skeleton);

        fetch(state.apiBase + '/conversations?userId=' + encodeURIComponent(state.userId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                skeleton.remove();
                renderHistoryList(data.conversations || []);
            })
            .catch(function () {
                // Never fabricate conversations the member never had — an
                // unreachable history reads as an error, not as content.
                skeleton.remove();
                var existing = container.querySelector('.mya-history-list-wrap');
                if (existing) existing.remove();
                container.appendChild(blankState(
                    ICONS.info,
                    "Couldn't load your conversations",
                    'Your history is safe — the connection just dropped. Try again in a moment.',
                    'Retry',
                    loadConversationHistory
                ));
            });
    }

    /**
     * Bucket a conversation by how long ago it was last touched. Real dates
     * only — a row with no usable timestamp falls into "Earlier" rather than
     * being labelled "Recent" like everything else used to be.
     */
    function historyBucket(conv) {
        var raw = conv.updatedAt || conv.createdAt;
        var d = raw ? new Date(String(raw).replace(' ', 'T')) : null;
        if (!d || isNaN(d.getTime())) return { key: 4, label: 'Earlier', date: null };

        var startOfToday = new Date();
        startOfToday.setHours(0, 0, 0, 0);
        var days = Math.floor((startOfToday.getTime() - d.getTime()) / 86400000);

        if (days < 0) return { key: 0, label: 'Today', date: d };
        if (days === 0) return { key: 1, label: 'Yesterday', date: d };
        if (days < 7) return { key: 2, label: 'Previous 7 Days', date: d };
        if (days < 30) return { key: 3, label: 'Previous 30 Days', date: d };
        return { key: 4, label: 'Earlier', date: d };
    }

    function relativeTime(date) {
        if (!date) return '';
        var diff = Date.now() - date.getTime();
        var mins = Math.round(diff / 60000);
        if (mins < 1) return 'Just now';
        if (mins < 60) return mins + 'm ago';
        var hrs = Math.round(mins / 60);
        if (hrs < 24) return hrs + 'h ago';
        var days = Math.round(hrs / 24);
        if (days < 7) return days + 'd ago';
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function renderHistoryList(conversations) {
        var container = state.els.historyView;
        var existingList = container.querySelector('.mya-history-list-wrap');
        if (existingList) existingList.remove();

        var listWrap = el('div', 'mya-history-list-wrap');

        if (!conversations.length) {
            listWrap.appendChild(blankState(
                ICONS.messageDots,
                'No conversations yet',
                'Ask Mya something about your hair and it will show up here, ready to pick back up any time.',
                'Start a conversation',
                function () { switchView('chat'); }
            ));
            container.appendChild(listWrap);
            return;
        }

        // Newest first, then grouped by bucket in bucket order.
        var withBuckets = conversations.map(function (conv) {
            return { conv: conv, bucket: historyBucket(conv) };
        }).sort(function (a, b) {
            var ta = a.bucket.date ? a.bucket.date.getTime() : 0;
            var tb = b.bucket.date ? b.bucket.date.getTime() : 0;
            return tb - ta;
        });

        var currentBucket = null;
        var currentGroup = null;
        var index = 0;

        withBuckets.forEach(function (row) {
            if (!currentGroup || row.bucket.label !== currentBucket) {
                currentBucket = row.bucket.label;
                currentGroup = el('div', 'mya-history-group');
                currentGroup.appendChild(el('div', 'mya-history-group-title', esc(currentBucket)));
                listWrap.appendChild(currentGroup);
            }
            currentGroup.appendChild(buildHistoryItem(row.conv, row.bucket, index++));
        });

        var noHits = el('div', 'mya-history-nohits');
        noHits.style.display = 'none';
        noHits.appendChild(blankState(
            ICONS.search,
            'No matches',
            'No conversation titles match that search. Try a different word.'
        ));
        listWrap.appendChild(noHits);

        container.appendChild(listWrap);
    }

    function buildHistoryItem(conv, bucket, index) {
        var title = conv.summary || 'Hair Journey Discussion';
        var isCurrent = conv.id && conv.id === state.conversationId;

        var item = el('button', 'mya-history-item' + (isCurrent ? ' current' : ''));
        item.type = 'button';
        item.style.animationDelay = Math.min(index * 35, 280) + 'ms';
        item.setAttribute('data-search', String(title).toLowerCase());

        var meta = [];
        if (conv.messageCount) meta.push(conv.messageCount + (conv.messageCount === 1 ? ' message' : ' messages'));
        var rel = relativeTime(bucket.date);
        if (rel) meta.push(rel);

        item.innerHTML =
            '<span class="mya-history-ico">' + ICONS.messageDots + '</span>' +
            '<span class="mya-history-main">' +
            '  <span class="mya-history-t">' + esc(title) + '</span>' +
            '  <span class="mya-history-meta">' +
                 (isCurrent ? '<span class="mya-history-badge">Current</span><span class="sep"></span>' : '') +
                 meta.map(function (m, i) {
                     return (i ? '<span class="sep"></span>' : '') + '<span>' + esc(m) + '</span>';
                 }).join('') +
            '  </span>' +
            '</span>' +
            '<span class="mya-history-chev">' + ICONS.chevronRight + '</span>';

        item.addEventListener('click', function () { resumeConversation(conv.id); });
        return item;
    }

    function startNewConversation() {
        fetch(state.apiBase + '/conversations/new', { method: 'POST' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                state.conversationId = data.conversationId;
                safeStorageSet(CONVERSATION_ID_KEY, data.conversationId);
                state.els.stream.innerHTML = '';
                switchView('chat');
                renderEmptyState();
            })
            .catch(function () {
                var newId = uuid();
                state.conversationId = newId;
                safeStorageSet(CONVERSATION_ID_KEY, newId);
                state.els.stream.innerHTML = '';
                switchView('chat');
                renderEmptyState();
            });
    }

    function resumeConversation(convId) {
        state.conversationId = convId;
        safeStorageSet(CONVERSATION_ID_KEY, convId);
        state.els.stream.innerHTML = '';
        switchView('chat');

        var statusEl = appendStatusIndicator('Loading conversation history…');

        fetch(state.apiBase + '/conversations/' + encodeURIComponent(convId) + '?userId=' + encodeURIComponent(state.userId))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                statusEl.remove();
                var history = data.chatHistory || [];
                if (history.length === 0) {
                    renderEmptyState();
                    return;
                }
                var lastDayLabel = null;

                history.forEach(function (msg) {
                    var role = msg.role === 'user' ? 'user' : 'assistant';
                    var content = msg.content || '';
                    var at = null;

                    // User turns are stored with an ISO stamp prefix. Recover
                    // it for the timestamp and day rules before stripping it,
                    // so a resumed thread shows when it actually happened
                    // rather than the moment it was reopened.
                    if (role === 'user') {
                        var stamped = content.match(/^(\d{4}-\d{2}-\d{2}T[^\s]+) - User Said: /);
                        if (stamped) {
                            var parsed = new Date(stamped[1]);
                            if (!isNaN(parsed.getTime())) at = parsed;
                        }
                        content = content.replace(/^\d{4}-\d{2}-\d{2}T[^ ]+ - User Said: /, '');
                    }
                    if (!at && msg.timestamp) {
                        var ts = new Date(String(msg.timestamp).replace(' ', 'T'));
                        if (!isNaN(ts.getTime())) at = ts;
                    }

                    if (at) {
                        var label = dayLabel(at);
                        if (label !== lastDayLabel) {
                            appendDaySeparator(label);
                            lastDayLabel = label;
                        }
                    }

                    if (role === 'user') {
                        appendUserMessage(content, at);
                    } else {
                        var b = appendAssistantMessage('', at);
                        b.innerHTML = parseMarkdown(content);
                    }
                });
                scrollToBottom(state.els.stream);
            })
            .catch(function () {
                statusEl.remove();
                renderEmptyState();
            });
    }

    // ---- View 4: Mobile-First Profile Bottom Sheet / Modal ----

    function buildProfileSheet() {
        var sheet = document.createElement('div');
        sheet.className = 'mya-profile-sheet';

        var content = document.createElement('div');
        content.className = 'mya-profile-sheet-content';

        sheet.appendChild(content);

        sheet.addEventListener('click', function (e) {
            if (e.target === sheet) {
                closeProfileSheet();
            }
        });

        return sheet;
    }

    function openProfileSheet() {
        state.els.profileSheet.style.display = 'flex';
        renderProfileSheetContent();
        refreshJourney();
    }

    function closeProfileSheet() {
        state.els.profileSheet.style.display = 'none';
        // Reset nav item active state back to current view
        var navItems = state.els.navStrip.querySelectorAll('.mya-nav-item');
        navItems.forEach(function (item) {
            item.classList.toggle('active', item.getAttribute('data-view') === state.activeView);
        });
    }

    /**
     * View 4: Profile — the member's real strand record, momentum and goals.
     *
     * Everything here comes from the host platform. Fields the member hasn't
     * filled in render as an explicit invitation to add them; nothing is ever
     * substituted with a plausible-looking placeholder, because a wrong
     * porosity on this card would send Mya's whole regimen advice sideways.
     */
    function renderProfileSheetContent() {
        var content = state.els.profileSheet.querySelector('.mya-profile-sheet-content');
        content.innerHTML = '';

        var handle = el('div', 'mya-sheet-handle');
        content.appendChild(handle);

        if (!hasLocalPlatform()) {
            content.appendChild(blankState(
                ICONS.profile,
                'Your profile lives in your Hair Journey',
                'Open Mya from myhairjourney.ai to see your strand record, streak and goals.'
            ));
            appendSheetDone(content);
            return;
        }

        if (!state.journeyData) {
            var skel = el('div', '');
            skel.innerHTML =
                '<div style="display:flex;gap:13px;align-items:center;padding:2px 0 16px;">' +
                '  <div class="mya-skel" style="width:60px;height:60px;border-radius:50%;"></div>' +
                '  <div style="flex:1;"><div class="mya-skel" style="height:14px;width:56%;margin-bottom:8px;"></div>' +
                '  <div class="mya-skel" style="height:10px;width:38%;"></div></div></div>' +
                '<div class="mya-skel" style="height:74px;border-radius:16px;margin-bottom:9px;"></div>' +
                '<div class="mya-skel" style="height:110px;border-radius:16px;"></div>';
            content.appendChild(skel);
            refreshJourney();
            return;
        }

        var data = state.journeyData || {};
        var p = data.profile || {};
        var stats = data.stats || {};
        var overview = data.goalsOverview || {};
        var analytics = data.analytics || {};

        // --- Hero: avatar with a completion ring around it ---
        var pct = Math.max(0, Math.min(100, parseInt(p.completionPercentage, 10) || 0));
        var circ = 2 * Math.PI * 27;
        var avatar = p.avatarUrl || (state.experienceContext && state.experienceContext.avatarUrl) || '';
        var name = p.displayName || p.username || state.userName || 'Your Journey';

        var subBits = [];
        if (p.hairJourneyStage) subBits.push(esc(p.hairJourneyStage));
        if (data.dayCount) subBits.push('Day ' + esc(data.dayCount));
        else if (data.joinDate) {
            var jd = new Date(String(data.joinDate).replace(' ', 'T'));
            if (!isNaN(jd.getTime())) subBits.push('Since ' + jd.toLocaleDateString(undefined, { month: 'short', year: 'numeric' }));
        }

        var hero = el('div', 'mya-pf-hero mya-rise');
        hero.innerHTML =
            '<div class="mya-pf-ring">' +
            '  <svg width="60" height="60" viewBox="0 0 60 60" aria-hidden="true">' +
            '    <circle cx="30" cy="30" r="27" fill="none" stroke="' + COLORS.stoneAlt + '" stroke-width="3"/>' +
            '    <circle cx="30" cy="30" r="27" fill="none" stroke="' + COLORS.coralDark + '" stroke-width="3" stroke-linecap="round"' +
            '      stroke-dasharray="' + circ.toFixed(1) + '" stroke-dashoffset="' + (circ * (1 - pct / 100)).toFixed(1) + '"/>' +
            '  </svg>' +
            (avatar
                ? '<img src="' + esc(avatar) + '" alt="' + esc(name) + '" />'
                : '<div style="position:absolute;inset:5px;border-radius:50%;background:' + COLORS.lightCoral +
                  ';color:' + COLORS.coralDark + ';display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;">' +
                  esc((name || '?').charAt(0).toUpperCase()) + '</div>') +
            '</div>' +
            '<div style="flex:1;min-width:0;">' +
            '  <h3 class="mya-pf-name">' + esc(name) + '</h3>' +
            '  <div class="mya-pf-sub">' + (subBits.join(' &middot; ') || 'MYAVANA member') + '</div>' +
            '</div>' +
            (pct < 100
                ? '<span class="mya-pill" style="background:' + COLORS.lightCoral + ';">' + pct + '% done</span>'
                : '<span class="mya-pill" style="background:' + COLORS.emeraldBg + ';color:' + COLORS.emerald + ';">Complete</span>');
        content.appendChild(hero);

        // --- Momentum: streak, entries, level. Only what the host reported. ---
        var band = [];
        if (typeof stats.currentStreak === 'number') band.push({ num: stats.currentStreak, lbl: 'Day Streak' });
        band.push({ num: data.totalEntries != null ? data.totalEntries : (data.entries || []).length, lbl: 'Entries' });
        if (data.lengthGain != null) band.push({ num: (data.lengthGain > 0 ? '+' : '') + data.lengthGain, unit: 'in', lbl: 'Length Gain' });
        else if (typeof stats.longestStreak === 'number') band.push({ num: stats.longestStreak, lbl: 'Best Streak' });

        var bandEl = el('div', '');
        bandEl.innerHTML = '<div class="mya-band">' + band.slice(0, 3).map(function (c, idx) {
            return '<div class="mya-band-cell mya-rise" style="animation-delay:' + (60 + idx * 55) + 'ms">' +
                '<div class="mya-band-num">' + esc(c.num) + (c.unit ? '<small>' + esc(c.unit) + '</small>' : '') + '</div>' +
                '<div class="mya-band-lbl">' + esc(c.lbl) + '</div></div>';
        }).join('') + '</div>';
        content.appendChild(bandEl);

        // --- Level progress, when the platform runs gamification ---
        if (stats.level && stats.nextLevelPoints) {
            var pts = stats.totalPoints || 0;
            var lvlPct = Math.max(0, Math.min(100, Math.round((pts / stats.nextLevelPoints) * 100)));
            var lvl = el('div', 'mya-goal mya-rise');
            lvl.style.marginTop = '10px';
            lvl.innerHTML =
                '<div class="mya-goal-top"><span class="mya-goal-t">Level ' + esc(stats.level) +
                (stats.levelTitle ? ' &middot; ' + esc(stats.levelTitle) : '') + '</span>' +
                '<span class="mya-goal-p">' + esc(pts) + '/' + esc(stats.nextLevelPoints) + '</span></div>' +
                '<div class="mya-bar"><span data-w="' + lvlPct + '"></span></div>';
            content.appendChild(lvl);
        }

        // --- Strand record (HairID) ---
        content.appendChild(el('div', 'mya-section-title', ICONS.profile + '<span>Your HairID</span><span class="mya-rule"></span>'));

        var traits = [
            { k: 'Hair Type', v: p.hairType, field: 'hairType' },
            { k: 'Porosity', v: p.porosity, field: 'porosity' },
            { k: 'Density', v: p.density, field: 'density' },
            { k: 'Length', v: p.length ? p.length + (p.measurementUnit === 'cm' ? ' cm' : '"') : '', field: 'length' }
        ];
        if (data.currentLength != null) {
            traits[3] = { k: 'Current Length', v: data.currentLength + '"', field: 'length' };
        }

        var grid = el('div', 'mya-pf-grid');
        traits.forEach(function (t, idx) {
            var filled = !!t.v;
            var cell = el('div', 'mya-pf-cell mya-rise' + (filled ? '' : ' empty'));
            cell.style.animationDelay = (110 + idx * 45) + 'ms';
            cell.innerHTML = '<div class="mya-pf-k">' + esc(t.k) + '</div>' +
                '<div class="mya-pf-v' + (filled ? '' : ' todo') + '">' + (filled ? esc(t.v) : '+ Add') + '</div>';
            if (!filled) {
                cell.setAttribute('role', 'button');
                cell.setAttribute('tabindex', '0');
                cell.addEventListener('click', function () { navigateLocal('profile', { focus: t.field }); });
            }
            grid.appendChild(cell);
        });
        content.appendChild(grid);

        // The host's own templated reading of these traits — never an
        // in-chat strand analysis, which Mya does not perform.
        if (data.hairIdNote) {
            content.appendChild(el('div', 'mya-pf-note', ICONS.info + '<span>' + esc(data.hairIdNote) + '</span>'));
        }

        if ((p.concerns || []).length) {
            var concerns = el('div', 'mya-chips');
            concerns.style.marginTop = '10px';
            concerns.innerHTML = p.concerns.map(function (c) {
                return '<span class="mya-chip">' + esc(c) + '</span>';
            }).join('');
            content.appendChild(concerns);
        }

        // --- Active goals with their real recorded progress ---
        var activeGoals = overview.active || (data.goals || []).filter(function (g) {
            return (g.status || 'active') !== 'completed';
        });

        if (activeGoals.length) {
            content.appendChild(el('div', 'mya-section-title', ICONS.goal + '<span>Active Goals</span><span class="mya-rule"></span>'));
            activeGoals.slice(0, 3).forEach(function (goal, idx) {
                var prog = Math.max(0, Math.min(100, parseInt(goal.progress, 10) || 0));
                var meta = [];
                if (goal.category) meta.push(esc(goal.category));
                if (goal.target_date) {
                    var td = new Date(String(goal.target_date).replace(' ', 'T'));
                    if (!isNaN(td.getTime())) meta.push('by ' + td.toLocaleDateString(undefined, { month: 'short', year: 'numeric' }));
                }
                var g = el('div', 'mya-goal mya-rise');
                g.style.animationDelay = (150 + idx * 55) + 'ms';
                g.innerHTML =
                    '<div class="mya-goal-top"><span class="mya-goal-t">' + esc(goal.title || 'Hair Goal') + '</span>' +
                    '<span class="mya-goal-p">' + prog + '%</span></div>' +
                    '<div class="mya-bar"><span data-w="' + prog + '"></span></div>' +
                    (meta.length ? '<div style="font-size:10.5px;color:' + COLORS.muted + ';margin-top:7px;">' + meta.join(' &middot; ') + '</div>' : '');
                g.addEventListener('click', function () {
                    closeProfileSheet();
                    switchView('chat');
                    sendMessage('Where am I on my goal "' + (goal.title || '') + '"? It is at ' + prog + '% — what should I do next to move it forward?');
                });
                g.style.cursor = 'pointer';
                content.appendChild(g);
            });
        } else {
            var noGoals = el('div', 'mya-goal');
            noGoals.style.cursor = 'pointer';
            noGoals.innerHTML = '<div class="mya-goal-top"><span class="mya-goal-t" style="color:' + COLORS.coralDark + ';">+ Set your first hair goal</span></div>' +
                '<div style="font-size:11.5px;color:' + COLORS.muted + ';line-height:1.5;">A goal gives Mya something concrete to coach you toward.</div>';
            noGoals.addEventListener('click', function () { navigateLocal('routine'); });
            content.appendChild(noGoals);
        }

        // --- Earned badges only; locked ones stay collapsed behind a count ---
        var badges = data.badges || [];
        var earned = badges.filter(function (b) { return b.unlocked; });
        if (earned.length) {
            content.appendChild(el('div', 'mya-section-title', ICONS.sparkles + '<span>Earned</span><span class="mya-rule"></span>'));
            var bw = el('div', 'mya-badges');
            bw.innerHTML = earned.slice(0, 6).map(function (b) {
                return '<span class="mya-badge" title="' + esc(b.description) + '">' + esc(b.icon || '') + esc(b.name) + '</span>';
            }).join('') + (badges.length > earned.length
                ? '<span class="mya-badge locked">' + (badges.length - earned.length) + ' to unlock</span>' : '');
            content.appendChild(bw);
        }

        if (analytics.topMood && (data.entries || []).length >= 3) {
            content.appendChild(el('div', 'mya-pf-note',
                ICONS.leaf + '<span>Across ' + esc(analytics.totalEntries || (data.entries || []).length) +
                ' entries your hair reads most often as <strong>' + esc(analytics.topMood) + '</strong>.</span>'));
        }

        // --- Actions: real navigation into the platform, plus asking Mya ---
        content.appendChild(el('div', 'mya-section-title', ICONS.routine + '<span>Quick Actions</span><span class="mya-rule"></span>'));

        var actions = [
            { icon: ICONS.camera, label: 'Log a new journey entry', go: openLocalComposer },
            { icon: ICONS.routine, label: "Today's routine checklist", ask: "Show today's routine checklist" },
            { icon: ICONS.journal, label: 'Open my full timeline', nav: 'journey' },
            { icon: ICONS.profile, label: 'Edit my hair profile', nav: 'profile' }
        ];
        if (pct < 100 && (p.missingFields || []).length) {
            actions.unshift({
                icon: ICONS.sparkles,
                label: 'Complete my profile (' + p.missingFields.length + ' left)',
                nav: 'profile'
            });
        }

        actions.forEach(function (a) {
            var btn = el('button', 'mya-act');
            btn.type = 'button';
            btn.innerHTML = '<span style="display:flex;align-items:center;gap:9px;">' + a.icon +
                '<span>' + esc(a.label) + '</span></span>' + ICONS.chevronRight;
            btn.addEventListener('click', function () {
                if (a.go) { closeProfileSheet(); a.go(); return; }
                if (a.nav) { closeProfileSheet(); navigateLocal(a.nav); return; }
                closeProfileSheet();
                switchView('chat');
                sendMessage(a.ask);
            });
            content.appendChild(btn);
        });

        appendSheetDone(content);

        // Animate every progress bar in on the next frame so the widths
        // transition from 0 rather than painting already-filled.
        requestAnimationFrame(function () {
            content.querySelectorAll('.mya-bar span[data-w]').forEach(function (bar) {
                bar.style.width = bar.getAttribute('data-w') + '%';
            });
        });
    }

    function appendSheetDone(content) {
        var done = el('button', 'mya-action-btn primary');
        done.type = 'button';
        done.style.cssText = 'width:100%;justify-content:center;margin-top:14px;padding:11px;';
        done.innerHTML = ICONS.close + '<span>Done</span>';
        done.addEventListener('click', closeProfileSheet);
        content.appendChild(done);
    }

    // ---- Empty State & Context Starters ----

    // ---- Page awareness ----
    //
    // The Hair Journey is a single-page shell routed entirely by hash
    // (https://myhairjourney.ai/#routine). The host pushes its route through
    // setContext on every navigation; the hash is the fallback for a cold load
    // or a host that hasn't wired the bridge.

    var ROUTES = {
        home:      { label: 'Hair Journey' },
        today:     { label: 'Today Hub' },
        journey:   { label: 'Journey' },
        routine:   { label: 'Routine' },
        community: { label: 'Community' },
        profile:   { label: 'Strand DNA' },
        goals:     { label: 'Goals' }
    };

    function currentRoute() {
        var ctx = state.experienceContext || {};
        var candidates = [ctx.route, ctx.view, ctx.tab];

        for (var i = 0; i < candidates.length; i++) {
            var c = String(candidates[i] || '').toLowerCase();
            if (ROUTES[c]) return c;
            // Tolerate the older descriptive view names (today_hub, goals_overview…)
            for (var key in ROUTES) {
                if (ROUTES.hasOwnProperty(key) && c.indexOf(key) !== -1) return key;
            }
        }

        var hash = (window.location.hash || '').replace(/^#!?/, '').split('?')[0].toLowerCase();
        if (ROUTES[hash]) return hash;

        return 'home';
    }

    var STARTERS = {
        today: [
            { text: "What's on my plate today?", prompt: "What should I focus on for my hair today?" },
            { text: 'Log a 10-second scalp check-in', prompt: 'I want to log a quick scalp check-in for today' },
            { text: 'How is my streak looking?', prompt: 'How is my hair care consistency streak looking this week?' }
        ],
        journey: [
            { text: 'What has changed since I started?', prompt: 'Looking at my journey entries, what has actually changed since I started?' },
            { text: 'Document a progress photo', prompt: 'I want to add a progress photo to my journey' },
            { text: 'Record a length check', prompt: 'Help me record a new length check measurement' }
        ],
        routine: [
            { text: 'Walk me through wash day', prompt: 'Walk me through my wash day routine step by step' },
            { text: "Show today's checklist", prompt: "Show today's routine checklist" },
            { text: 'Why this deep conditioning step?', prompt: 'Why is the deep conditioning step important for my hair type and porosity?' }
        ],
        goals: [
            { text: 'Review my active goals', prompt: 'What are my current hair goals and how am I tracking against them?' },
            { text: 'Adjust a goal target', prompt: 'I want to review and adjust one of my hair goal targets' },
            { text: 'What moves the needle this week?', prompt: 'What should I focus on this week to move my goals forward?' }
        ],
        community: [
            { text: "What's trending for my texture?", prompt: 'What styles and routines are trending for my hair texture right now?' },
            { text: 'Help me share a milestone', prompt: 'Help me write a community post about my latest hair milestone' },
            { text: 'Find people with hair like mine', prompt: 'How do I find community members with a similar hair profile to mine?' }
        ],
        profile: [
            { text: 'Explain my porosity', prompt: 'Explain what my porosity and density mean for how I should care for my hair' },
            { text: 'Do my products match my hair?', prompt: 'Does my current product regimen match my hair porosity and texture?' },
            { text: 'Book a stylist consultation', prompt: 'I would like to book a 1-on-1 virtual stylist consultation' }
        ],
        home: [
            { text: "Today's humidity & frizz outlook", prompt: 'What is the hair weather forecast and humidity recommendation for my hair today?' },
            { text: "Show today's routine checklist", prompt: "Show today's routine checklist" },
            { text: 'Does my regimen match my hair?', prompt: 'Does my current product regimen match my hair porosity and texture?' }
        ]
    };

    function getContextualStarters() {
        return STARTERS[currentRoute()] || STARTERS.home;
    }

    function renderEmptyState() {
        var container = state.els.stream;
        container.innerHTML = '';

        var wrap = document.createElement('div');
        wrap.className = 'mya-empty-state';
        wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;text-align:center;padding:28px 16px 20px;';

        var greetingName = state.userName || 'there';
        var hour = new Date().getHours();
        var timeOfDay = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

        // The streak line only appears when the platform actually reported one.
        var streak = state.journeyData && state.journeyData.stats
            ? state.journeyData.stats.currentStreak : null;
        var sub = (typeof streak === 'number' && streak > 0)
            ? "You're on a <strong style=\"color:" + COLORS.onyx + ';">' + streak + '-day consistency streak.</strong> Let\'s keep it going.'
            : 'Ask me anything about your hair — I read from your Hair Journey as we talk.';

        wrap.innerHTML = [
            getMyaLogoHtml(56, 'glow'),
            '<h3 style="font-size:20px;font-weight:700;color:' + COLORS.onyx + ';margin:12px 0 6px;letter-spacing:-0.02em;">' + timeOfDay + ', ' + esc(greetingName) + '</h3>',
            '<p style="font-size:13px;color:' + COLORS.muted + ';line-height:1.55;max-width:320px;margin:0 0 18px;">' + sub + '</p>',
            '<div style="font-size:11px;font-weight:700;color:' + COLORS.blueberry + ';text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">Suggested Next Steps</div>'
        ].join('');

        var startersGrid = document.createElement('div');
        startersGrid.style.cssText = 'display:flex;flex-direction:column;gap:8px;width:100%;max-width:340px;';

        getContextualStarters().forEach(function (starter, idx) {
            var btn = document.createElement('button');
            btn.className = 'mya-starter-btn mya-rise';
            btn.type = 'button';
            btn.style.cssText = 'background:#ffffff;border:1px solid ' + COLORS.border + ';color:' + COLORS.onyx + ';border-radius:12px;padding:10px 14px;font-size:12.5px;font-weight:500;text-align:left;cursor:pointer;display:flex;align-items:center;gap:8px;transition:transform 0.2s cubic-bezier(0.22,1,0.36,1),border-color 0.2s ease,box-shadow 0.2s ease;box-shadow:0 1px 4px rgba(0,0,0,0.03);animation-delay:' + (idx * 70) + 'ms;';
            btn.innerHTML = ICONS.sparkles + '<span>' + esc(starter.text) + '</span>';
            btn.addEventListener('mouseenter', function () {
                btn.style.transform = 'translateY(-2px)';
                btn.style.borderColor = COLORS.coral;
                btn.style.boxShadow = '0 8px 20px rgba(34,35,35,0.07)';
            });
            btn.addEventListener('mouseleave', function () {
                btn.style.transform = '';
                btn.style.borderColor = COLORS.border;
                btn.style.boxShadow = '0 1px 4px rgba(0,0,0,0.03)';
            });
            btn.addEventListener('click', function () {
                wrap.remove();
                sendMessage(starter.prompt);
            });
            startersGrid.appendChild(btn);
        });

        wrap.appendChild(startersGrid);
        container.appendChild(wrap);
    }

    function updateContextUI() {
        if (!state.els.contextText) return;
        var route = currentRoute();
        var label = ROUTES[route] ? ROUTES[route].label : 'Hair Journey';
        if (route === 'home' && state.userName) {
            label = state.userName + "'s Journey";
        }
        state.els.contextText.textContent = label;
    }

    function toggleExpand() {
        state.expanded = !state.expanded;
        state.els.panel.classList.toggle('is-expanded', state.expanded);
        state.els.expandBtn.innerHTML = state.expanded ? ICONS.compress : ICONS.expand;
        state.els.expandBtn.setAttribute('title', state.expanded ? 'Compress workspace' : 'Expand workspace');
    }

    function togglePanel() {
        if (state.open) closeEntryDetail();
        state.open = !state.open;
        state.els.panel.style.display = state.open ? 'flex' : 'none';

        // The launcher morphs into the workspace rather than sitting under it.
        // Reclaiming that corner is what lets the panel drop to bottom:24px and
        // take back the ~64px the button used to occupy.
        if (state.els.launcher) {
            state.els.launcher.classList.toggle('is-morphed', state.open);
        }

        if (state.open) {
            autosizeComposer();
            if (state.els.stream.children.length === 0) {
                renderEmptyState();
            }
            state.els.textarea.focus();
        }
    }

    function toggleDrawer(force) {
        state.drawerOpen = typeof force === 'boolean' ? force : !state.drawerOpen;
        state.els.drawer.style.display = state.drawerOpen ? 'block' : 'none';
    }

    // ---- Voice Input / Dictation via Gemini 3.5 Transcribe ----

    function toggleVoiceInput() {
        if (state.audioListening) {
            stopDictation();
        } else {
            startDictation();
        }
    }

    function startDictation() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            fallbackSpeechRecognition();
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function (stream) {
                state.dictationStream = stream;
                state.dictationChunks = [];

                var mimeType = 'audio/webm';
                if (window.MediaRecorder && !MediaRecorder.isTypeSupported('audio/webm')) {
                    if (MediaRecorder.isTypeSupported('audio/mp4')) mimeType = 'audio/mp4';
                    else mimeType = '';
                }

                var options = mimeType ? { mimeType: mimeType } : {};
                var recorder = new MediaRecorder(stream, options);
                state.dictationRecorder = recorder;

                recorder.ondataavailable = function (e) {
                    if (e.data && e.data.size > 0) {
                        state.dictationChunks.push(e.data);
                    }
                };

                recorder.onstop = function () {
                    if (state.dictationStream) {
                        state.dictationStream.getTracks().forEach(function (t) { t.stop(); });
                        state.dictationStream = null;
                    }
                    processDictationAudio();
                };

                recorder.start(250);
                state.audioListening = true;
                state.els.micBtn.classList.add('active');
                state.els.audioWave.style.display = 'flex';
                state.els.textarea.placeholder = 'Listening to your voice... (tap mic to finish)';
            })
            .catch(function (err) {
                console.warn('[Mya Voice] Mic access failed, falling back:', err);
                fallbackSpeechRecognition();
            });
    }

    function stopDictation() {
        if (state.dictationRecorder && state.dictationRecorder.state !== 'inactive') {
            state.audioListening = false;
            state.els.micBtn.classList.remove('active');
            state.els.audioWave.style.display = 'none';
            state.els.textarea.placeholder = 'Transcribing with Gemini 3.5...';
            state.dictationRecorder.stop();
        } else {
            state.audioListening = false;
            state.els.micBtn.classList.remove('active');
            state.els.audioWave.style.display = 'none';
            state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
        }
    }

    function processDictationAudio() {
        if (!state.dictationChunks || state.dictationChunks.length === 0) {
            state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
            return;
        }

        var blobType = (state.dictationRecorder && state.dictationRecorder.mimeType) || 'audio/webm';
        var audioBlob = new Blob(state.dictationChunks, { type: blobType });
        state.dictationChunks = [];

        var reader = new FileReader();
        reader.onload = function () {
            var base64Data = (reader.result || '').split(',')[1];
            if (!base64Data) {
                state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
                return;
            }

            fetch(state.apiBase + '/audio/transcribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    audioData: base64Data,
                    mimeType: blobType,
                    customVocabulary: ['HHCP', 'porosity', 'type 4A', 'type 4B', 'type 4C', 'pre-poo', 'clarifying cleanse', 'Candace', 'Mya', 'HairAI']
                })
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
                    if (data && data.success && data.text) {
                        var existing = state.els.textarea.value.trim();
                        state.els.textarea.value = existing ? (existing + ' ' + data.text) : data.text;
                        state.els.textarea.style.height = 'auto';
                        autosizeComposer();
                        state.els.sendBtn.disabled = false;
                    }
                })
                .catch(function (err) {
                    console.error('[Mya Voice] Transcription error:', err);
                    state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
                });
        };
        reader.readAsDataURL(audioBlob);
    }

    function fallbackSpeechRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            var recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            state.audioListening = true;
            state.els.micBtn.classList.add('active');
            state.els.audioWave.style.display = 'flex';
            state.els.textarea.placeholder = 'Listening to your voice...';

            recognition.onresult = function (event) {
                var transcript = event.results[0][0].transcript;
                var existing = state.els.textarea.value.trim();
                state.els.textarea.value = existing ? (existing + ' ' + transcript) : transcript;
                state.els.textarea.style.height = 'auto';
                autosizeComposer();
                state.els.sendBtn.disabled = false;
                state.audioListening = false;
                state.els.micBtn.classList.remove('active');
                state.els.audioWave.style.display = 'none';
                state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
            };
            recognition.onerror = function () {
                state.audioListening = false;
                state.els.micBtn.classList.remove('active');
                state.els.audioWave.style.display = 'none';
                state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
            };
            recognition.start();
        } else {
            state.els.textarea.placeholder = 'Microphone not supported in this browser';
            setTimeout(function () {
                state.els.textarea.placeholder = 'Ask Mya about your hair journey...';
            }, 2000);
        }
    }

    // ---- Gemini Mobile App-Style Live Voice Chat Overlay ----

    function buildLiveOverlay() {
        var overlay = document.createElement('div');
        overlay.className = 'mya-live-overlay';

        var topBar = document.createElement('div');
        topBar.className = 'mya-live-top-bar';

        var statusPill = document.createElement('div');
        statusPill.className = 'mya-live-status-pill';
        statusPill.innerHTML = '<span class="mya-live-dot"></span><span class="mya-live-timer">00:00</span>';

        var title = document.createElement('div');
        title.style.cssText = 'font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;';
        title.innerHTML = getMyaLogoHtml(24) + '<span>Mya Live Voice</span>';

        var closeBtn = document.createElement('button');
        closeBtn.className = 'mya-btn-icon';
        closeBtn.style.color = '#ffffff';
        closeBtn.innerHTML = ICONS.close;
        closeBtn.setAttribute('title', 'End Live Voice Call');
        closeBtn.addEventListener('click', endLiveVoice);

        topBar.appendChild(statusPill);
        topBar.appendChild(title);
        topBar.appendChild(closeBtn);

        var center = document.createElement('div');
        center.style.cssText = 'flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;margin:12px 0;';

        var canvasWrap = document.createElement('div');
        canvasWrap.className = 'mya-live-canvas-wrap';
        canvasWrap.setAttribute('title', 'Tap to interrupt');
        canvasWrap.addEventListener('click', function () {
            if (state.liveVoiceState === 'speaking' || state.liveVoiceState === 'thinking') {
                interruptMya();
            }
        });

        var canvas = document.createElement('canvas');
        canvas.className = 'mya-live-canvas';
        canvas.width = 480;
        canvas.height = 480;
        canvasWrap.appendChild(canvas);

        var stateBanner = document.createElement('div');
        stateBanner.className = 'mya-live-state-banner';
        stateBanner.innerHTML = '<span>● Listening</span> <span style="opacity:0.7;font-weight:400;">• Speak naturally</span>';

        center.appendChild(canvasWrap);
        center.appendChild(stateBanner);

        var tickerCard = document.createElement('div');
        tickerCard.className = 'mya-live-ticker-card';
        tickerCard.innerHTML = '<div class="mya-live-ticker-role" style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:' + COLORS.coral + ';margin-bottom:3px;">Mya Live</div><div class="mya-live-ticker-text" style="color:rgba(255,255,255,0.95);">Say something to Mya to start live voice chat...</div>';

        var controls = document.createElement('div');
        controls.className = 'mya-live-controls';

        var muteBtn = document.createElement('button');
        muteBtn.className = 'mya-live-btn mya-live-btn-icon-only';
        muteBtn.innerHTML = ICONS.mic;
        muteBtn.setAttribute('title', 'Toggle mute');
        muteBtn.addEventListener('click', toggleLiveMute);

        var interruptBtn = document.createElement('button');
        interruptBtn.className = 'mya-live-btn';
        interruptBtn.innerHTML = ICONS.stop + '<span>Interrupt</span>';
        interruptBtn.setAttribute('title', 'Tap to interrupt Mya');
        interruptBtn.addEventListener('click', interruptMya);

        var endBtn = document.createElement('button');
        endBtn.className = 'mya-live-btn-end';
        endBtn.innerHTML = ICONS.close + '<span>End Call</span>';
        endBtn.addEventListener('click', endLiveVoice);

        controls.appendChild(muteBtn);
        controls.appendChild(interruptBtn);
        controls.appendChild(endBtn);

        overlay.appendChild(topBar);
        overlay.appendChild(center);
        overlay.appendChild(tickerCard);
        overlay.appendChild(controls);

        return overlay;
    }

    function setLiveVoiceState(newState) {
        state.liveVoiceState = newState;
        if (!state.els.liveDot || !state.els.liveStateBanner) return;

        state.els.liveDot.className = 'mya-live-dot' + (newState === 'thinking' ? ' thinking' : (newState === 'speaking' ? ' speaking' : ''));

        if (newState === 'listening') {
            state.els.liveStateBanner.innerHTML = '<span>● Listening</span> <span style="opacity:0.7;font-weight:400;">• Speak naturally</span>';
        } else if (newState === 'thinking') {
            state.els.liveStateBanner.innerHTML = '<span>● Thinking</span> <span style="opacity:0.7;font-weight:400;">• Checking your Hair Journey...</span>';
        } else if (newState === 'speaking') {
            state.els.liveStateBanner.innerHTML = '<span>● Speaking</span> <span style="opacity:0.7;font-weight:400;">• Tap anywhere to interrupt</span>';
        }
    }

    function drawLiveOrb() {
        if (state.liveVoiceState === 'idle') return;

        var canvas = state.els.liveCanvas;
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (!ctx) return;

        var width = canvas.width;
        var height = canvas.height;
        var cx = width / 2;
        var cy = height / 2;

        var avg = 0;
        if (state.liveVoiceAnalyser && !state.liveVoiceMuted) {
            var freqData = new Uint8Array(state.liveVoiceAnalyser.frequencyBinCount);
            state.liveVoiceAnalyser.getByteFrequencyData(freqData);
            var sum = 0;
            for (var i = 0; i < freqData.length; i++) sum += freqData[i];
            avg = sum / freqData.length;

            if (state.liveVoiceState === 'speaking' && avg > 36) {
                state.bargeInFrames = (state.bargeInFrames || 0) + 1;
                if (state.bargeInFrames >= 4) {
                    state.bargeInFrames = 0;
                    interruptMya();
                }
            } else {
                state.bargeInFrames = 0;
            }
        }

        ctx.clearRect(0, 0, width, height);
        var now = performance.now() * 0.002;

        if (state.liveVoiceState === 'thinking') {
            var coreGrad = ctx.createRadialGradient(cx, cy, 10, cx, cy, 80);
            coreGrad.addColorStop(0, 'rgba(255, 255, 255, 0.95)');
            coreGrad.addColorStop(0.4, 'rgba(231, 166, 144, 0.7)');
            coreGrad.addColorStop(1, 'rgba(231, 166, 144, 0)');
            ctx.fillStyle = coreGrad;
            ctx.beginPath();
            ctx.arc(cx, cy, 80, 0, Math.PI * 2);
            ctx.fill();

            for (var r = 1; r <= 3; r++) {
                ctx.save();
                ctx.translate(cx, cy);
                ctx.rotate(now * (r % 2 === 0 ? 1 : -1) * (0.8 / r));
                ctx.strokeStyle = r === 1 ? 'rgba(231, 166, 144, 0.8)' : (r === 2 ? 'rgba(238, 236, 225, 0.6)' : 'rgba(255, 255, 255, 0.4)');
                ctx.lineWidth = 2.5;
                ctx.beginPath();
                ctx.ellipse(0, 0, 70 + r * 18, (70 + r * 18) * 0.55, 0, 0, Math.PI * 2);
                ctx.stroke();
                ctx.restore();
            }
        } else {
            var isSpeaking = state.liveVoiceState === 'speaking';
            var pulse = isSpeaking ? (Math.sin(now * 5) * 14 + 16) : (avg * 0.6);
            var baseR = 72 + pulse;

            var waves = [
                { color: 'rgba(231, 166, 144, 0.85)', width: 3.5, blur: 20, speed: 1.2, count: 6, amp: 8 + pulse * 0.3 },
                { color: 'rgba(238, 236, 225, 0.75)', width: 2.5, blur: 14, speed: -0.9, count: 5, amp: 6 + pulse * 0.2 },
                { color: 'rgba(255, 255, 255, 0.6)', width: 2.0, blur: 10, speed: 1.5, count: 7, amp: 4 + pulse * 0.15 }
            ];

            for (var w = 0; w < waves.length; w++) {
                var wave = waves[w];
                ctx.save();
                ctx.strokeStyle = wave.color;
                ctx.lineWidth = wave.width;
                ctx.beginPath();

                var steps = 100;
                for (var s = 0; s <= steps; s++) {
                    var theta = (s / steps) * Math.PI * 2;
                    var offset = Math.sin(theta * wave.count + now * wave.speed * 3) * wave.amp;
                    var rad = baseR + offset;
                    var x = cx + Math.cos(theta) * rad;
                    var y = cy + Math.sin(theta) * rad;
                    if (s === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                }
                ctx.closePath();
                ctx.stroke();
                ctx.restore();
            }

            var coreGrad2 = ctx.createRadialGradient(cx, cy, 8, cx, cy, 45 + pulse * 0.2);
            coreGrad2.addColorStop(0, '#ffffff');
            coreGrad2.addColorStop(0.5, COLORS.lightCoral);
            coreGrad2.addColorStop(1, 'rgba(231, 166, 144, 0.1)');
            ctx.fillStyle = coreGrad2;
            ctx.beginPath();
            ctx.arc(cx, cy, 45 + pulse * 0.2, 0, Math.PI * 2);
            ctx.fill();
        }

        state.liveVoiceRaf = requestAnimationFrame(drawLiveOrb);
    }

    function initLiveSpeechRecognition() {
        if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) return null;
        var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        var recognition = new SpeechRecognition();
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        recognition.onresult = function (event) {
            if (state.liveVoiceState === 'speaking') interruptMya();
            var interim = '';
            var final = '';
            for (var i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) final += event.results[i][0].transcript;
                else interim += event.results[i][0].transcript;
            }
            var transcript = (final || interim).trim();
            if (transcript) {
                state.els.liveTickerRole.textContent = 'YOU';
                state.els.liveTickerText.textContent = transcript;
            }
            if (final && final.trim().length > 0) {
                handleLiveUserUtterance(final.trim());
            }
        };

        recognition.onerror = function () {};
        recognition.onend = function () {
            if (state.liveVoiceState !== 'idle' && !state.liveVoiceMuted) {
                try { recognition.start(); } catch (e) {}
            }
        };
        return recognition;
    }

    function handleLiveUserUtterance(text) {
        if (!text || state.liveVoiceState === 'thinking') return;
        state.liveTurns.push({ role: 'user', content: text, timestamp: new Date().toISOString() });
        setLiveVoiceState('thinking');

        var accumulated = '';
        fetch(state.apiBase + '/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/x-ndjson, text/plain;q=0.9'
            },
            body: JSON.stringify({
                message: text,
                from: state.userId,
                groupId: state.conversationId,
                experienceContext: getContextPayload()
            })
        })
            .then(function (res) {
                if (!res.ok || !res.body) throw new Error('Stream error');
                var reader = res.body.getReader();
                var decoder = new TextDecoder();
                var lineBuffer = '';

                function pump() {
                    return reader.read().then(function (result) {
                        if (result.done) {
                            speakLiveAssistantResponse(accumulated);
                            return;
                        }
                        var chunk = decoder.decode(result.value, { stream: true });
                        lineBuffer += chunk;
                        var newlineIdx;
                        while ((newlineIdx = lineBuffer.indexOf('\n')) !== -1) {
                            var line = lineBuffer.slice(0, newlineIdx).trim();
                            lineBuffer = lineBuffer.slice(newlineIdx + 1);
                            if (line) {
                                try {
                                    var frame = JSON.parse(line);
                                    if (frame.t === 'delta' && frame.text) {
                                        accumulated += frame.text;
                                        state.els.liveTickerRole.textContent = 'MYA';
                                        state.els.liveTickerText.textContent = accumulated;
                                    } else if (frame.t === 'block' && frame.block) {
                                        state.liveBlocks.push(frame.block);
                                    }
                                } catch (e) {
                                    accumulated += line;
                                }
                            }
                        }
                        return pump();
                    });
                }
                return pump();
            })
            .catch(function () {
                speakLiveAssistantResponse("I'm here with you. What would you like to explore next in your Hair Journey?");
            });
    }

    function speakLiveAssistantResponse(text) {
        if (!text || state.liveVoiceState === 'idle') return;
        state.liveTurns.push({ role: 'assistant', content: text, timestamp: new Date().toISOString() });
        setLiveVoiceState('speaking');
        state.els.liveTickerRole.textContent = 'MYA';
        state.els.liveTickerText.textContent = text;

        var clean = text.replace(/[*#_`]/g, '').replace(/https?:\/\/\S+/g, '').trim();

        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
            var utt = new SpeechSynthesisUtterance(clean);
            utt.rate = 1.05;
            utt.pitch = 1.0;
            utt.onend = function () {
                if (state.liveVoiceState === 'speaking') setLiveVoiceState('listening');
            };
            utt.onerror = function () {
                if (state.liveVoiceState === 'speaking') setLiveVoiceState('listening');
            };
            window.speechSynthesis.speak(utt);
        } else {
            setTimeout(function () {
                if (state.liveVoiceState === 'speaking') setLiveVoiceState('listening');
            }, 3000);
        }
    }

    function interruptMya() {
        if (state.liveVoiceState === 'speaking' || state.liveVoiceState === 'thinking') {
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
            setLiveVoiceState('listening');
            state.els.liveTickerRole.textContent = 'Mya Live';
            state.els.liveTickerText.textContent = '(Interrupted) Listening to you...';
        }
    }

    function toggleLiveMute() {
        state.liveVoiceMuted = !state.liveVoiceMuted;
        if (state.liveVoiceStream) {
            state.liveVoiceStream.getAudioTracks().forEach(function (track) {
                track.enabled = !state.liveVoiceMuted;
            });
        }
        state.els.liveMuteBtn.innerHTML = state.liveVoiceMuted ? ICONS.micMute : ICONS.mic;
    }

    function startLiveVoice() {
        if (!state.open) togglePanel();
        state.liveTurns = [];
        state.liveBlocks = [];
        state.liveVoiceDuration = 0;
        state.liveVoiceMuted = false;
        state.bargeInFrames = 0;

        state.els.liveOverlay.style.display = 'flex';
        state.els.liveTimer.textContent = '00:00';
        state.els.liveTickerRole.textContent = 'Mya Live';
        state.els.liveTickerText.textContent = 'Say something to Mya to start live voice chat...';
        setLiveVoiceState('listening');

        state.liveVoiceTimer = setInterval(function () {
            state.liveVoiceDuration += 1;
            var mins = Math.floor(state.liveVoiceDuration / 60);
            var secs = state.liveVoiceDuration % 60;
            state.els.liveTimer.textContent = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
        }, 1000);

        try {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                state.liveVoiceAudioCtx = new AudioCtx();
                state.liveVoiceAnalyser = state.liveVoiceAudioCtx.createAnalyser();
                state.liveVoiceAnalyser.fftSize = 128;
            }
        } catch (e) {}

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(function (stream) {
                    state.liveVoiceStream = stream;
                    if (state.liveVoiceAudioCtx && state.liveVoiceAnalyser) {
                        var source = state.liveVoiceAudioCtx.createMediaStreamSource(stream);
                        source.connect(state.liveVoiceAnalyser);
                    }
                    state.liveVoiceRecognition = initLiveSpeechRecognition();
                    if (state.liveVoiceRecognition) {
                        try { state.liveVoiceRecognition.start(); } catch (e) {}
                    }
                    drawLiveOrb();
                })
                .catch(function () {
                    drawLiveOrb();
                });
        } else {
            drawLiveOrb();
        }
    }

    function endLiveVoice() {
        if (state.liveVoiceState === 'idle') return;
        if (state.liveVoiceTimer) clearInterval(state.liveVoiceTimer);
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        if (state.liveVoiceStream) {
            state.liveVoiceStream.getTracks().forEach(function (t) { t.stop(); });
            state.liveVoiceStream = null;
        }
        if (state.liveVoiceAudioCtx) {
            try { state.liveVoiceAudioCtx.close(); } catch (e) {}
            state.liveVoiceAudioCtx = null;
        }
        if (state.liveVoiceRecognition) {
            try { state.liveVoiceRecognition.stop(); } catch (e) {}
            state.liveVoiceRecognition = null;
        }
        if (state.liveVoiceRaf) cancelAnimationFrame(state.liveVoiceRaf);

        var turns = state.liveTurns.slice();
        var blocks = state.liveBlocks.slice();
        var dur = state.liveVoiceDuration || 0;

        state.liveVoiceState = 'idle';
        state.els.liveOverlay.style.display = 'none';

        if (turns.length > 0) {
            fetch(state.apiBase + '/chat/live/commit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    userId: state.userId,
                    conversationId: state.conversationId,
                    turns: turns
                })
            }).catch(function () {});

            var empty = state.els.stream.querySelector('.mya-empty-state');
            if (empty) empty.remove();

            var summaryCard = document.createElement('div');
            summaryCard.className = 'mya-card';
            summaryCard.style.background = 'linear-gradient(135deg,' + COLORS.onyx + ' 0%,' + COLORS.onyxSoft + ' 100%)';
            summaryCard.style.color = '#ffffff';
            summaryCard.innerHTML = [
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">',
                '  <div style="font-weight:700;font-size:13.5px;display:flex;align-items:center;gap:6px;">' + getMyaLogoHtml(20) + '<span>Live Voice Call Completed</span></div>',
                '  <span class="mya-pill" style="background:rgba(231,166,144,0.25);color:' + COLORS.lightCoral + ';">Gemini Live</span>',
                '</div>',
                '<div style="font-size:12px;color:rgba(255,255,255,0.8);margin-bottom:8px;">All recommendations have been documented in your Hair Journey.</div>',
                '<div style="display:flex;gap:12px;font-size:11.5px;color:' + COLORS.lightCoral + ';">',
                '  <span>Duration: <strong>' + dur + 's</strong></span>',
                '  <span>Dialogue: <strong>' + turns.length + ' turns</strong></span>',
                '</div>'
            ].join('');
            state.els.stream.appendChild(summaryCard);

            turns.forEach(function (turn) {
                if (turn.role === 'user') appendUserMessage(turn.content);
                else {
                    var b = appendAssistantMessage('');
                    b.innerHTML = parseMarkdown(turn.content);
                }
            });

            blocks.forEach(function (block) {
                renderBlock(block);
            });

            scrollToBottom(state.els.stream);
        }
    }

    // ---- Photo Journey Staging ----

    function handleFileInput(e) {
        var file = e.target.files && e.target.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function (evt) {
            stagePhoto({
                file: file,
                previewUrl: evt.target.result,
                category: 'Journal entry',
                caption: 'Wash day — ' + new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
            });
        };
        reader.readAsDataURL(file);
    }

    function stagePhoto(photoData) {
        state.stagedPhoto = photoData;
        var container = state.els.photoStage;
        container.innerHTML = '';
        container.style.display = 'block';

        var removeBtn = document.createElement('button');
        removeBtn.className = 'mya-btn-icon';
        removeBtn.style.cssText = 'position:absolute;top:6px;right:6px;width:22px;height:22px;background:rgba(0,0,0,0.4);color:#fff;';
        removeBtn.innerHTML = ICONS.close;
        removeBtn.addEventListener('click', function () {
            state.stagedPhoto = null;
            container.style.display = 'none';
            state.els.sendBtn.disabled = state.els.textarea.value.trim().length === 0;
        });

        var previewWrap = document.createElement('div');
        previewWrap.style.cssText = 'display:flex;gap:10px;align-items:center;';

        var img = document.createElement('img');
        img.src = photoData.previewUrl;
        img.style.cssText = 'width:56px;height:56px;border-radius:8px;object-fit:cover;border:1px solid ' + COLORS.border + ';';

        var info = document.createElement('div');
        info.innerHTML = '<div style="font-size:12px;font-weight:700;color:' + COLORS.onyx + ';">Document Your Journey</div>' +
            '<div style="font-size:10.5px;color:' + COLORS.muted + ';">Visual progress entry</div>';

        previewWrap.appendChild(img);
        previewWrap.appendChild(info);

        var captionInput = document.createElement('input');
        captionInput.style.cssText = 'width:100%;margin-top:8px;padding:6px 10px;border-radius:8px;border:1px solid ' + COLORS.border + ';font-size:12px;outline:none;';
        captionInput.value = photoData.caption;
        captionInput.addEventListener('input', function () {
            if (state.stagedPhoto) state.stagedPhoto.caption = this.value;
        });

        container.appendChild(removeBtn);
        container.appendChild(previewWrap);
        container.appendChild(captionInput);

        state.els.sendBtn.disabled = false;
        state.els.textarea.focus();
    }

    function submitComposer() {
        var text = state.els.textarea.value.trim();
        var photo = state.stagedPhoto;
        if (!text && !photo) return;

        state.els.textarea.value = '';
        autosizeComposer();
        state.els.sendBtn.disabled = true;

        var empty = state.els.stream.querySelector('.mya-empty-state');
        if (empty) empty.remove();

        if (photo) {
            state.stagedPhoto = null;
            state.els.photoStage.style.display = 'none';
            appendUserPhotoMessage(photo, text);
            executePhotoUpload(photo, text);
        } else {
            sendMessage(text);
        }
    }

    function appendUserPhotoMessage(photo, caption) {
        var row = document.createElement('div');
        row.className = 'mya-msg-row';
        row.innerHTML = '<div class="mya-msg-header" style="justify-content:flex-end;">You</div>';

        var bubble = document.createElement('div');
        bubble.className = 'mya-msg user';
        bubble.innerHTML = [
            '<div style="font-size:11px;font-weight:700;color:' + COLORS.lightCoral + ';text-transform:uppercase;margin-bottom:4px;">' + photo.category + '</div>',
            '<img src="' + photo.previewUrl + '" style="width:100%;max-width:220px;border-radius:10px;object-fit:cover;margin:4px 0;" />',
            caption ? '<div style="margin-top:4px;">' + caption + '</div>' : ''
        ].join('');

        row.appendChild(bubble);
        state.els.stream.appendChild(row);
        scrollToBottom(state.els.stream);
    }

    function executePhotoUpload(photo, caption) {
        var statusEl = appendStatusIndicator('Recording visual progress to Hair Journey…');
        setTimeout(function () {
            statusEl.remove();
            appendAssistantMessage("I've saved this as a visual progress entry for " + (photo.caption || 'your wash day') + ". Would you like to add a note about how your hair felt today?");
        }, 1200);
    }

    // ---- Markdown & Message Rendering ----

    /**
     * Minimal, escape-first markdown. Lists matter here: hair guidance is
     * mostly ordered steps and bulleted product notes, and before this they
     * rendered as literal "- " and "1. " inside a paragraph.
     */
    function parseMarkdown(text) {
        if (!text) return '';

        var safe = String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Inline pass. Bold before italic so "**x**" isn't eaten by the
        // single-asterisk rule.
        function inline(str) {
            return str
                .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>');
        }

        var lines = safe.split('\n');
        var out = [];
        var listBuffer = [];
        var listType = null;
        var paraBuffer = [];

        function flushList() {
            if (!listBuffer.length) return;
            out.push('<' + listType + '>' + listBuffer.map(function (li) {
                return '<li>' + inline(li) + '</li>';
            }).join('') + '</' + listType + '>');
            listBuffer = [];
            listType = null;
        }

        function flushPara() {
            if (!paraBuffer.length) return;
            out.push('<p>' + paraBuffer.map(inline).join('<br/>') + '</p>');
            paraBuffer = [];
        }

        lines.forEach(function (line) {
            var trimmed = line.trim();

            if (!trimmed) { flushList(); flushPara(); return; }

            var heading = trimmed.match(/^(#{1,4})\s+(.*)$/);
            if (heading) {
                flushList(); flushPara();
                out.push('<h' + Math.min(4, Math.max(3, heading[1].length)) + '>' + inline(heading[2]) + '</h' +
                    Math.min(4, Math.max(3, heading[1].length)) + '>');
                return;
            }

            if (/^(---+|___+|\*\*\*+)$/.test(trimmed)) {
                flushList(); flushPara();
                out.push('<hr/>');
                return;
            }

            var quote = trimmed.match(/^&gt;\s*(.*)$/);
            if (quote) {
                flushList(); flushPara();
                out.push('<blockquote>' + inline(quote[1]) + '</blockquote>');
                return;
            }

            var ordered = trimmed.match(/^\d+[.)]\s+(.*)$/);
            if (ordered) {
                flushPara();
                if (listType && listType !== 'ol') flushList();
                listType = 'ol';
                listBuffer.push(ordered[1]);
                return;
            }

            var bullet = trimmed.match(/^[-*•]\s+(.*)$/);
            if (bullet) {
                flushPara();
                if (listType && listType !== 'ul') flushList();
                listType = 'ul';
                listBuffer.push(bullet[1]);
                return;
            }

            flushList();
            paraBuffer.push(trimmed);
        });

        flushList();
        flushPara();
        return out.join('');
    }

    // ---- Message rendering ----

    /**
     * Consecutive turns from one speaker are grouped: the name row prints
     * once for the run, and the bubbles tuck together. A long thread should
     * read as a conversation, not a stack of labelled boxes.
     */
    function shouldGroup(role) {
        var rows = state.els.stream.querySelectorAll('.mya-msg-row');
        if (!rows.length) return false;
        var last = rows[rows.length - 1];
        // Anything between the turns (a card, a checklist, a status pill)
        // breaks the run.
        if (last.nextElementSibling) return false;
        return last.getAttribute('data-role') === role;
    }

    function clockTime(date) {
        return (date || new Date()).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    }

    function buildMessageRow(role, headerHtml, at) {
        var grouped = shouldGroup(role);
        var row = document.createElement('div');
        row.className = 'mya-msg-row' + (grouped ? ' grouped' : '');
        row.setAttribute('data-role', role);

        if (!grouped) {
            var header = document.createElement('div');
            header.className = 'mya-msg-header';
            if (role === 'user') header.style.justifyContent = 'flex-end';
            header.innerHTML = headerHtml + '<span class="mya-msg-time">' + esc(clockTime(at)) + '</span>';
            row.appendChild(header);
        }
        return row;
    }

    function appendUserMessage(text, at) {
        var row = buildMessageRow('user', '<span>You</span>', at);
        var bubble = document.createElement('div');
        bubble.className = 'mya-msg user';
        bubble.textContent = text;
        row.appendChild(bubble);
        state.els.stream.appendChild(row);
        scrollToBottom(state.els.stream);
        return bubble;
    }

    function appendAssistantMessage(initialText, at) {
        var row = buildMessageRow('assistant', getMyaLogoHtml(18) + '<span>Mya</span>', at);
        var bubble = document.createElement('div');
        bubble.className = 'mya-msg assistant';
        bubble.innerHTML = parseMarkdown(initialText || '');
        row.appendChild(bubble);

        // Copy is only offered once a reply has finished streaming.
        var tools = document.createElement('div');
        tools.className = 'mya-msg-tools';
        var copyBtn = document.createElement('button');
        copyBtn.className = 'mya-msg-tool';
        copyBtn.type = 'button';
        copyBtn.innerHTML = ICONS.copy + '<span>Copy</span>';
        copyBtn.addEventListener('click', function () {
            copyText(bubble.innerText || bubble.textContent || '', copyBtn);
        });
        tools.appendChild(copyBtn);
        row.appendChild(tools);

        state.els.stream.appendChild(row);
        scrollToBottom(state.els.stream);
        return bubble;
    }

    function copyText(text, btn) {
        function done() {
            var original = btn.innerHTML;
            btn.classList.add('done');
            btn.innerHTML = ICONS.check + '<span>Copied</span>';
            window.setTimeout(function () {
                btn.classList.remove('done');
                btn.innerHTML = original;
            }, 1600);
        }

        if (window.navigator && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () {});
            return;
        }

        // execCommand fallback for non-secure contexts.
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0;';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            done();
        } catch (err) { /* clipboard unavailable */ }
    }

    /**
     * Marks a bubble as actively streaming so a caret blinks at the tail.
     */
    function setStreamingCaret(bubble, on) {
        if (!bubble) return;
        var existing = bubble.querySelector('.mya-caret');
        if (!on) {
            if (existing) existing.remove();
            return;
        }
        if (existing) existing.remove();
        var caret = document.createElement('span');
        caret.className = 'mya-caret';
        var last = bubble.lastElementChild;
        (last || bubble).appendChild(caret);
    }

    function appendStatusIndicator(message) {
        var indicator = document.createElement('div');
        indicator.className = 'mya-status-indicator';
        indicator.innerHTML = getMyaLogoHtml(16) +
            '<span class="mya-status-shimmer">' + esc(message) + '</span>' +
            '<span class="mya-status-dots"><i></i><i></i><i></i></span>';
        state.els.stream.appendChild(indicator);
        scrollToBottom(state.els.stream);
        return indicator;
    }

    function dayLabel(date) {
        var startOfToday = new Date();
        startOfToday.setHours(0, 0, 0, 0);
        var days = Math.floor((startOfToday.getTime() - date.getTime()) / 86400000);
        if (days < 0) return 'Today';
        if (days === 0) return 'Yesterday';
        if (days < 7) return date.toLocaleDateString(undefined, { weekday: 'long' });
        return date.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    }

    /**
     * A "Today"/date rule, printed when a resumed conversation crosses days.
     */
    function appendDaySeparator(label) {
        var sep = document.createElement('div');
        sep.className = 'mya-day-sep';
        sep.innerHTML = '<span>' + esc(label) + '</span>';
        state.els.stream.appendChild(sep);
    }

    // ---- Generative UI Cards ----

    function renderGoalCard(block) {
        var data = block.data || {};
        var card = document.createElement('div');
        card.className = 'mya-card mya-goal-card';
        var pct = data.progressPercent || 72;

        card.innerHTML = [
            '<div class="mya-card-header">',
            '  <div>',
            '    <span class="mya-pill">' + (data.category || 'Growth & Retention') + '</span>',
            '    <h4 class="mya-card-title">' + (data.title || 'Grow and retain length') + '</h4>',
            '  </div>',
            '  <span class="mya-goal-pct">' + pct + '%</span>',
            '</div>',
            '<div class="mya-progress-track"><div class="mya-progress-bar" style="width:' + pct + '%;"></div></div>',
            '<div class="mya-goal-meta">',
            '  <span>Target: <strong>' + (data.targetMetric || '4 inches') + '</strong></span>',
            '  <span>Target Date: <strong>' + (data.targetDate || 'December 15, 2026') + '</strong></span>',
            '</div>',
            '<div class="mya-card-actions">',
            '  <button class="mya-action-btn primary adjust-goal-btn">' + ICONS.goal + ' <span>Adjust goal</span></button>',
            '  <button class="mya-action-btn view-history-btn">' + ICONS.history + ' <span>Progress history</span></button>',
            '</div>'
        ].join('');

        card.querySelector('.adjust-goal-btn').addEventListener('click', function () {
            renderMutationConfirmation({
                title: 'Adjust Target Date for ' + (data.title || 'Growth Goal'),
                currentValue: data.targetDate || 'December 15, 2026',
                proposedValue: 'February 15, 2027',
                callback: function () {
                    sendMessage('Confirm updating target date for ' + (data.title || 'Growth Goal') + ' to February 15, 2027');
                }
            });
        });

        card.querySelector('.view-history-btn').addEventListener('click', function () {
            sendMessage('Show my progress history for ' + (data.title || 'my goal'));
        });

        state.els.stream.appendChild(card);
        scrollToBottom(state.els.stream);
    }

    function renderChecklist(block) {
        var data = block.data || {};
        var card = document.createElement('div');
        card.className = 'mya-card';

        card.innerHTML = [
            '<div class="mya-card-header">',
            '  <div>',
            '    <span class="mya-pill">' + (data.category || 'Daily Regimen') + '</span>',
            '    <h4 class="mya-card-title">' + (data.title || 'Today\'s Routine Steps') + '</h4>',
            '  </div>',
            '</div>'
        ].join('');

        var steps = data.steps || [
            { id: 1, title: 'Cleanse with sulfate-free shampoo', duration: '15m', completed: true },
            { id: 2, title: 'Deep conditioning treatment', duration: '30m', completed: false },
            { id: 3, title: 'Moisturize & seal ends', duration: '10m', completed: false },
            { id: 4, title: 'Protective styling', duration: '20m', completed: false }
        ];

        var listContainer = document.createElement('div');
        var syncBadge = document.createElement('div');
        syncBadge.className = 'mya-synced-badge';
        syncBadge.style.display = 'none';
        syncBadge.innerHTML = ICONS.check + '<span>Synced with Hair Journey</span>';

        steps.forEach(function (step) {
            var item = document.createElement('div');
            item.className = 'mya-checklist-item' + (step.completed ? ' is-done' : '');

            item.innerHTML = [
                '<div style="display:flex;align-items:center;">',
                '  <span class="mya-check-box">' + (step.completed ? ICONS.check : '') + '</span>',
                '  <span class="mya-check-text">' + step.title + '</span>',
                '</div>',
                step.duration ? '<span style="font-size:10.5px;color:' + COLORS.muted + ';">' + step.duration + '</span>' : ''
            ].join('');

            item.addEventListener('click', function () {
                if (!item.classList.contains('is-done')) {
                    item.classList.add('is-done');
                    item.querySelector('.mya-check-box').innerHTML = ICONS.check;
                    syncBadge.style.display = 'inline-flex';
                    sendMessage('I completed ' + step.title);
                }
            });

            listContainer.appendChild(item);
        });

        card.appendChild(listContainer);
        card.appendChild(syncBadge);
        state.els.stream.appendChild(card);
        scrollToBottom(state.els.stream);
    }

    function renderHairProfile(block) {
        var data = block.data || {};
        var card = document.createElement('div');
        card.className = 'mya-card';

        card.innerHTML = [
            '<div class="mya-card-header">',
            '  <div>',
            '    <span class="mya-pill">Official HHCP</span>',
            '    <h4 class="mya-card-title">Verified Strand DNA</h4>',
            '  </div>',
            '</div>',
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px;">',
            '  <div style="background:' + COLORS.stone + ';padding:8px 12px;border-radius:10px;font-size:11.5px;">Hair Texture<strong>' + (data.hairType || 'Type 4C (Coily)') + '</strong></div>',
            '  <div style="background:' + COLORS.stone + ';padding:8px 12px;border-radius:10px;font-size:11.5px;">Porosity<strong>' + (data.porosity || 'Normal Porosity') + '</strong></div>',
            '  <div style="background:' + COLORS.stone + ';padding:8px 12px;border-radius:10px;font-size:11.5px;">Density<strong>' + (data.density || 'Medium Density') + '</strong></div>',
            '  <div style="background:' + COLORS.stone + ';padding:8px 12px;border-radius:10px;font-size:11.5px;">Elasticity<strong>' + (data.elasticity || 'High Elasticity') + '</strong></div>',
            '</div>'
        ].join('');

        state.els.stream.appendChild(card);
        scrollToBottom(state.els.stream);
    }

    function renderConsultationCard(block) {
        var data = block.data || {};
        var card = document.createElement('div');
        card.className = 'mya-consult-card';

        card.innerHTML = [
            '<span class="mya-pill" style="background:' + COLORS.coral + ';color:' + COLORS.onyx + ';">Licensed Stylist Guidance</span>',
            '<h4>' + (data.title || 'A little extra guidance may help') + '</h4>',
            '<p>' + (data.description || 'Since this breakage concern has persisted across multiple wash days, speaking directly with a licensed MYAVANA stylist will give you tailored clarity.') + '</p>',
            '<button class="mya-consult-btn">' + ICONS.sparkles + ' <span>Book 1-on-1 Consultation</span></button>'
        ].join('');

        card.querySelector('.mya-consult-btn').addEventListener('click', function () {
            window.open(data.bookingUrl || '/consultations', '_blank');
        });

        state.els.stream.appendChild(card);
        scrollToBottom(state.els.stream);
    }

    function renderMutationConfirmation(opts) {
        var card = document.createElement('div');
        card.className = 'mya-card';
        card.style.border = '1.5px solid ' + COLORS.coral;

        card.innerHTML = [
            '<div style="font-size:13.5px;font-weight:700;color:' + COLORS.onyx + ';margin-bottom:8px;display:flex;align-items:center;gap:6px;">' + getMyaLogoHtml(18) + '<span>' + opts.title + '</span></div>',
            '<div style="background:' + COLORS.stone + ';border-radius:10px;padding:10px;margin-bottom:10px;font-size:12px;display:flex;flex-direction:column;gap:4px;">',
            '  <div>' + opts.currentValue + '</div>',
            '  <div style="color:' + COLORS.coralDark + ';font-weight:600;">Proposed: ' + opts.proposedValue + '</div>',
            '</div>',
            '<div class="mya-card-actions">',
            '  <button class="mya-action-btn primary confirm-btn">' + ICONS.check + ' <span>Confirm Update</span></button>',
            '  <button class="mya-action-btn cancel-btn">' + ICONS.close + ' <span>Cancel</span></button>',
            '</div>'
        ].join('');

        card.querySelector('.confirm-btn').addEventListener('click', function () {
            card.innerHTML = '<div style="font-size:12.5px;font-weight:600;color:' + COLORS.emerald + ';display:flex;align-items:center;gap:5px;">' + ICONS.check + ' <span>Goal updated • Synced with Hair Journey</span></div>';
            if (typeof opts.callback === 'function') opts.callback();
        });

        card.querySelector('.cancel-btn').addEventListener('click', function () {
            card.remove();
        });

        state.els.stream.appendChild(card);
        scrollToBottom(state.els.stream);
    }

    function renderQuickReplies(actions) {
        if (!actions || actions.length === 0) return;
        var wrap = el('div', 'mya-qr-row');

        actions.forEach(function (act, idx) {
            var label = typeof act === 'string' ? act : (act.label || act.text || '');
            if (!label) return;
            var pill = el('button', 'mya-qr');
            pill.type = 'button';
            pill.style.animationDelay = (idx * 55) + 'ms';
            pill.textContent = label;
            pill.addEventListener('click', function () {
                wrap.remove();
                sendMessage(label);
            });
            wrap.appendChild(pill);
        });

        state.els.stream.appendChild(wrap);
        scrollToBottom(state.els.stream);
    }

    function renderBlock(block) {
        if (!block || !block.type) return;
        switch (block.type) {
            case 'goal_card':
                renderGoalCard(block);
                break;
            case 'today_checklist':
            case 'routine_checklist':
                renderChecklist(block);
                break;
            case 'hair_profile_summary':
                renderHairProfile(block);
                break;
            case 'consultation_card':
                renderConsultationCard(block);
                break;
            case 'quick_replies':
                renderQuickReplies(block.data?.replies || block.actions || []);
                break;
            default:
                break;
        }
    }

    // ---- Transport & NDJSON Streaming ----

    function isNearBottom(container) {
        return container.scrollHeight - container.scrollTop - container.clientHeight < 60;
    }

    function scrollToBottom(container) {
        container.scrollTop = container.scrollHeight;
    }

    var COMPOSER_MIN_H = 24;
    var COMPOSER_MAX_H = 132;

    /**
     * Size the composer to its content, both directions.
     *
     * Measuring requires collapsing to 0 first — reading scrollHeight at the
     * current height only ever reports the same or more, which is why a
     * composer that grows never shrinks back on its own.
     */
    function autosizeComposer() {
        var ta = state.els.textarea;
        if (!ta) return;

        ta.style.height = '0px';
        var needed = ta.scrollHeight;
        var height = Math.max(COMPOSER_MIN_H, Math.min(needed, COMPOSER_MAX_H));
        ta.style.height = height + 'px';

        // Only scroll once it has actually hit the ceiling, so there's no
        // stray scrollbar on a one-line draft.
        ta.classList.toggle('is-scrolling', needed > COMPOSER_MAX_H);
    }

    /**
     * While a reply streams, the send button reads as busy rather than
     * inviting another message that would be dropped by the isStreaming guard.
     */
    function setComposerStreaming(on) {
        var btn = state.els.sendBtn;
        if (!btn) return;
        btn.disabled = !!on;
        btn.setAttribute('aria-label', on ? 'Mya is replying' : 'Send message');
        btn.innerHTML = on
            ? '<span class="mya-status-dots" style="--d:#fff"><i></i><i></i><i></i></span>'
            : ICONS.send;
        if (state.els.textarea) {
            state.els.textarea.setAttribute('placeholder', on ? 'Mya is replying…' : 'Ask Mya about your hair journey...');
        }
    }

    function getContextPayload() {
        var route = currentRoute();
        return Object.assign({
            surface: window.location.hostname || 'myhairjourney.ai',
            page: (window.location.pathname || '/') + (window.location.hash || ''),
            title: document.title || 'Myavana Hair Journey'
        }, state.experienceContext || {}, {
            route: route,
            view: route,
            routeLabel: ROUTES[route] ? ROUTES[route].label : 'Hair Journey'
        });
    }

    function sendMessage(text) {
        if (!text || state.isStreaming) return;

        if (state.activeView !== 'chat') {
            switchView('chat');
        }

        var empty = state.els.stream.querySelector('.mya-empty-state');
        if (empty) empty.remove();

        appendUserMessage(text);
        var statusEl = appendStatusIndicator('Checking your Hair Journey…');
        var assistantBubble = null;
        var accumulatedRaw = '';

        state.isStreaming = true;
        setComposerStreaming(true);

        fetch(state.apiBase + '/chat/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/x-ndjson, text/plain;q=0.9'
            },
            body: JSON.stringify({
                message: text,
                from: state.userId,
                groupId: state.conversationId,
                experienceContext: getContextPayload()
            })
        })
            .then(function (res) {
                if (!res.ok || !res.body) throw new Error('Stream failed: ' + res.status);
                var reader = res.body.getReader();
                var decoder = new TextDecoder();
                var lineBuffer = '';

                function pump() {
                    return reader.read().then(function (result) {
                        if (result.done) {
                            state.isStreaming = false;
                            setStreamingCaret(assistantBubble, false);
                            setComposerStreaming(false);
                            if (statusEl) statusEl.remove();
                            return;
                        }

                        var chunk = decoder.decode(result.value, { stream: true });
                        lineBuffer += chunk;

                        var newlineIdx;
                        while ((newlineIdx = lineBuffer.indexOf('\n')) !== -1) {
                            var line = lineBuffer.slice(0, newlineIdx).trim();
                            lineBuffer = lineBuffer.slice(newlineIdx + 1);

                            if (line) {
                                try {
                                    var frame = JSON.parse(line);
                                    if (frame.t === 'delta' && frame.text) {
                                        if (statusEl) { statusEl.remove(); statusEl = null; }
                                        if (!assistantBubble) assistantBubble = appendAssistantMessage('');
                                        accumulatedRaw += frame.text;
                                        assistantBubble.innerHTML = parseMarkdown(accumulatedRaw);
                                        setStreamingCaret(assistantBubble, true);
                                        // Only follow the tail if the reader hasn't scrolled up
                                        // to re-read something earlier in the thread.
                                        if (state.els.atBottom) scrollToBottom(state.els.stream);
                                    } else if (frame.t === 'block' && frame.block) {
                                        renderBlock(frame.block);
                                    } else if (frame.t === 'action' && frame.actions) {
                                        renderQuickReplies(frame.actions);
                                    }
                                } catch (err) {
                                    if (statusEl) { statusEl.remove(); statusEl = null; }
                                    if (!assistantBubble) assistantBubble = appendAssistantMessage('');
                                    accumulatedRaw += line;
                                    assistantBubble.innerHTML = parseMarkdown(accumulatedRaw);
                                    scrollToBottom(state.els.stream);
                                }
                            }
                        }
                        return pump();
                    });
                }
                return pump();
            })
            .catch(function (err) {
                console.error('[Mya Widget] Stream error:', err);
                state.isStreaming = false;
                setStreamingCaret(assistantBubble, false);
                setComposerStreaming(false);
                if (statusEl) statusEl.remove();
                if (!assistantBubble) {
                    appendAssistantMessage("I'm having a little trouble connecting to your Hair Journey right now. Please try again in a moment.");
                }
            });
    }

    function logEvent(eventType, metadata) {
        if (!state.apiBase || !state.userId) return;
        fetch(state.apiBase + '/events', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                userId: state.userId,
                conversationId: state.conversationId,
                eventType: eventType,
                metadata: metadata || {}
            }),
            keepalive: true
        }).catch(function () {});
    }

    function setContext(context) {
        if (!context || typeof context !== 'object') return;

        if (context.wpUserId) state.userId = String(context.wpUserId);
        if (context.firstName) state.userName = context.firstName;
        else if (context.userName) state.userName = context.userName;
        if (context.streak) state.streak = context.streak;

        var previousRoute = state.els.contextText ? currentRoute() : null;
        state.experienceContext = Object.assign({}, state.experienceContext, context);
        updateContextUI();

        // The starter pills are route-specific, so a navigation while the
        // empty state is showing has to re-render it — otherwise Mya keeps
        // offering Today's suggestions on the Routine page.
        var routeChanged = previousRoute !== null && previousRoute !== currentRoute();
        if (state.els.stream && state.els.stream.querySelector('.mya-empty-state')) {
            renderEmptyState();
        }

        // A route change usually means the member just created or edited
        // something on the platform; re-pull so Stories and Profile aren't
        // showing a stale copy the next time they're opened.
        if (routeChanged && hasLocalPlatform()) {
            refreshJourney(true);
        }
    }

    function init(options) {
        options = options || {};
        if (!options.apiBase) throw new Error('MyavanaWidget.init requires { apiBase }');
        state.apiBase = options.apiBase.replace(/\/$/, '');
        state.position = options.position === 'bottom-right' ? 'bottom-right' : 'bottom-left';

        // Auto-detect platform or use provided option
        var detectedPlatform = 'web';
        if (typeof window !== 'undefined' && (window.myavanaSettings || window.myavanaNextData || window.MyavanaNext)) {
            detectedPlatform = 'wordpress';
        }
        state.platform = options.platform || detectedPlatform;

        // Platform-Adaptive Capabilities
        if (options.capabilities) {
            state.capabilities = Object.assign({}, state.capabilities, options.capabilities);
        } else if (state.platform === 'wordpress') {
            // WordPress Hair Journey supports local stories, profile, and photo entries
            state.capabilities.stories = true;
            state.capabilities.profile = true;
            state.capabilities.photoJourney = true;
        } else {
            // Standalone web / generic embed defaults to Global Core only (Chat, History, Live Voice)
            state.capabilities.stories = false;
            state.capabilities.profile = false;
            state.capabilities.photoJourney = false;
        }

        if (options.adapter || options.localPlatform) {
            registerLocalPlatform(options.adapter || options.localPlatform);
        }

        if (options.context) setContext(options.context);

        ensureIdentity();
        injectStyles();
        buildWidget();

        console.log('%c Mya ' + BUILD + ' ', 'background:#222323;color:#e7a690;border-radius:3px;');
    }

    window.MyavanaWidget = {
        build: BUILD,
        init: init,
        setContext: setContext,
        registerLocalPlatform: registerLocalPlatform,
        registerPlatformAdapter: registerLocalPlatform, // SDK standard alias
        setCapabilities: function (caps) {
            if (caps && typeof caps === 'object') {
                Object.assign(state.capabilities, caps);
                applyLocalPlatformNav();
            }
        },
        getCapabilities: function () {
            return Object.assign({}, state.capabilities);
        },
        setPlatform: function (platformName) {
            state.platform = platformName;
            applyLocalPlatformNav();
        },
        getPlatform: function () {
            return state.platform;
        },
        refreshJourney: function () { return refreshJourney(true); },
        logEvent: logEvent,
        open: function () { if (!state.open) togglePanel(); },
        close: function () { if (state.open) togglePanel(); },
        toggleExpand: toggleExpand,
        switchView: switchView,
        openStories: function () {
            if (state.capabilities.stories && (hasLocalPlatform() || state.platform === 'wordpress')) {
                if (!state.open) togglePanel();
                switchView('stories');
            }
        },
        openHistory: function () { if (!state.open) togglePanel(); switchView('history'); },
        sendMessage: sendMessage,
        startLiveVoice: startLiveVoice,
        endLiveVoice: endLiveVoice,
        openProfile: openProfileSheet
    };

})(window, document);
