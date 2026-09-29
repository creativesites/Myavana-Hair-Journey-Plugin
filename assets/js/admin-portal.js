(function () {
    const settings = window.myavanaAdminPortal;
    const root = document.getElementById('myavanaAdminPortalApp');

    if (!settings || !root) {
        return;
    }

    const state = {
        activeSection: (settings.sections && settings.sections[0] && settings.sections[0].id) || 'overview',
        selectedUserId: null,
        loadingUsers: false,
    };

    const sectionMap = new Map((settings.sections || []).map((section) => [section.id, section]));

    function endpoint(path) {
        return `${settings.restRoot}${path}`.replace(/([^:]\/)\/+/g, '$1');
    }

    async function request(path, options = {}) {
        const response = await fetch(endpoint(path), {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': settings.nonce,
                ...(options.headers || {}),
            },
            ...options,
        });

        const payload = await response.json();
        if (!response.ok || payload.success === false) {
            throw new Error(payload.message || 'Request failed.');
        }

        return payload.data;
    }

    function safeText(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderSectionButtons(className) {
        return (settings.sections || []).map((section) => `
            <button type="button" data-section="${section.id}" class="${section.id === state.activeSection ? 'is-active' : ''}">
                <span>${safeText(section.label)}</span>
                ${className === 'myavana-admin-sidebar__nav' ? '<span>→</span>' : ''}
            </button>
        `).join('');
    }

    function renderShell() {
        root.innerHTML = `
            <div class="myavana-admin-portal-layout">
                <aside class="myavana-admin-sidebar">
                    <span class="myavana-admin-sidebar__eyebrow">MYAVANA Admin</span>
                    <h1 class="myavana-admin-sidebar__title">Admin Portal</h1>
                    <p class="myavana-admin-sidebar__copy">Manage members, intelligence, community activity, gamification, and operations from one workspace.</p>
                    <nav class="myavana-admin-sidebar__nav" id="myavanaAdminPortalNav"></nav>
                    <div class="myavana-admin-sidebar__actions">
                        <a href="${safeText(settings.homeUrl || '/')}" class="myavana-admin-sidebar__home-link">← Back to Home</a>
                    </div>
                    <div class="myavana-admin-sidebar__meta">
                        <strong>${safeText(settings.currentUser.name)}</strong><br>
                        ${safeText(settings.currentUser.email)}<br>
                        Staff workspace
                    </div>
                </aside>
                <main class="myavana-admin-main">
                    <section class="myavana-admin-workspace-menu">
                        <div class="myavana-admin-workspace-menu__label">Workspace Menu</div>
                        <nav class="myavana-admin-workspace-menu__nav" id="myavanaAdminPortalWorkspaceMenu"></nav>
                    </section>
                    <section class="myavana-admin-panel" id="myavanaOverviewPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaAiPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaOperationsPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaJourneyPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaCommunityPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaGamificationPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaUsersPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaSettingsPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaControlPanel" hidden></section>
                    <section class="myavana-admin-panel" id="myavanaAuditPanel" hidden></section>
                </main>
            </div>
        `;

        const nav = root.querySelector('#myavanaAdminPortalNav');
        const workspaceNav = root.querySelector('#myavanaAdminPortalWorkspaceMenu');
        nav.innerHTML = renderSectionButtons('myavana-admin-sidebar__nav');
        workspaceNav.innerHTML = renderSectionButtons('myavana-admin-workspace-menu__nav');

        const handleSectionClick = (event) => {
            const button = event.target.closest('button[data-section]');
            if (!button) {
                return;
            }

            state.activeSection = button.dataset.section;
            syncActiveSection();
        };

        nav.addEventListener('click', handleSectionClick);
        workspaceNav.addEventListener('click', handleSectionClick);
    }

    function syncActiveSection() {
        root.querySelectorAll('#myavanaAdminPortalNav button, #myavanaAdminPortalWorkspaceMenu button').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.section === state.activeSection);
        });

        ['overview', 'ai', 'operations', 'journey', 'community', 'gamification', 'users', 'settings', 'control', 'audit'].forEach((sectionId) => {
            const panel = root.querySelector(`#myavana${sectionId.charAt(0).toUpperCase()}${sectionId.slice(1)}Panel`);
            if (panel) {
                panel.hidden = sectionId !== state.activeSection;
            }
        });
    }

    function renderOverview(data) {
        const panel = root.querySelector('#myavanaOverviewPanel');
        const cards = (data.cards || []).map((card) => `
            <article class="myavana-admin-stat-card">
                <div class="myavana-admin-stat-card__label">${safeText(card.label)}</div>
                <div class="myavana-admin-stat-card__value">${safeText(card.value)}</div>
                <div class="myavana-admin-stat-card__meta">${safeText(card.meta)}</div>
            </article>
        `).join('');

        const funnelRows = (data.highlights?.funnel || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.label)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.count)} users</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.conversion)}%</span>
            </div>
        `).join('');

        const actions = (data.highlights?.behavior?.top_actions || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.event_name)}</p>
                    <p class="myavana-admin-row__meta">Tracked interaction</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.total)}</span>
            </div>
        `).join('');

        const aiPages = (data.highlights?.ai?.top_pages || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.page_context)}</p>
                    <p class="myavana-admin-row__meta">AI components generated</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.total)}</span>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Overview</h3>
                    <p class="myavana-admin-panel__desc">Live platform health pulled from analytics, behavior, AI, and moderation data.</p>
                </div>
                <div class="myavana-admin-subtle">${safeText(data.range?.date_from)} to ${safeText(data.range?.date_to)}</div>
            </div>
            <div class="myavana-admin-card-grid">${cards}</div>
            <div class="myavana-admin-section-grid" style="margin-top:18px;">
                <div class="myavana-admin-panel" style="padding:0; box-shadow:none; background:transparent; border:0;">
                    <div class="myavana-admin-panel__header">
                        <div>
                            <h4 class="myavana-admin-panel__title">Funnel Snapshot</h4>
                            <p class="myavana-admin-panel__desc">Immediate conversion visibility for signup-to-usage behavior.</p>
                        </div>
                    </div>
                    <div class="myavana-admin-list">${funnelRows || '<div class="myavana-admin-callout">No funnel data available yet.</div>'}</div>
                </div>
                <div class="myavana-admin-panel" style="padding:0; box-shadow:none; background:transparent; border:0;">
                    <div class="myavana-admin-panel__header">
                        <div>
                            <h4 class="myavana-admin-panel__title">Top Tracked Actions</h4>
                            <p class="myavana-admin-panel__desc">Behavior stream highlights from instrumented sessions.</p>
                        </div>
                    </div>
                    <div class="myavana-admin-list">${actions || '<div class="myavana-admin-callout">Behavior tracking data has not populated yet.</div>'}</div>
                </div>
            </div>
            <div class="myavana-admin-panel" style="margin-top:18px;">
                <div class="myavana-admin-panel__header">
                    <div>
                        <h4 class="myavana-admin-panel__title">AI Surface Summary</h4>
                        <p class="myavana-admin-panel__desc">Where AI components are currently most active.</p>
                    </div>
                    <span class="myavana-admin-pill">${safeText(data.highlights?.ai?.generated)} generated</span>
                </div>
                <div class="myavana-admin-list">${aiPages || '<div class="myavana-admin-callout">AI activity will appear here once the intelligence layer starts generating components.</div>'}</div>
            </div>
        `;
    }

    function renderUsers(data) {
        const panel = root.querySelector('#myavanaUsersPanel');
        const users = (data.items || []).map((user) => `
            <article class="myavana-admin-user-card" data-user-id="${user.id}">
                <div class="myavana-admin-user-card__header">
                    <div>
                        <h4 class="myavana-admin-user-card__name">${safeText(user.display_name)}</h4>
                        <div class="myavana-admin-user-card__detail">${safeText(user.user_email)}</div>
                    </div>
                    <span class="myavana-admin-pill">${safeText(user.onboarding_status)}</span>
                </div>
                <div class="myavana-admin-user-metrics">
                    <div class="myavana-admin-user-metric"><strong>${safeText(user.goal_count)}</strong><span>Goals</span></div>
                    <div class="myavana-admin-user-metric"><strong>${safeText(user.routine_count)}</strong><span>Routines</span></div>
                    <div class="myavana-admin-user-metric"><strong>${safeText((user.roles || []).join(', '))}</strong><span>Roles</span></div>
                </div>
                <button type="button" class="myavana-admin-button myavana-admin-button--ghost" data-view-user="${user.id}">View details</button>
            </article>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Users</h3>
                    <p class="myavana-admin-panel__desc">Early support and member-ops workspace for the portal rollout.</p>
                </div>
                <a href="${endpoint('export')}?type=users&_wpnonce=${settings.nonce}" class="myavana-admin-button myavana-admin-button--ghost" target="_blank">Export CSV</a>
            </div>
            <div class="myavana-admin-users-controls">
                <div class="myavana-admin-users-search">
                    <input type="search" id="myavanaAdminUserSearch" placeholder="Search name, email, or username">
                    <button type="button" class="myavana-admin-button" id="myavanaAdminUserSearchButton">Search</button>
                </div>
                <div id="myavanaAdminUserDetail"></div>
                <div class="myavana-admin-user-list">${users || '<div class="myavana-admin-callout">No users matched the current search.</div>'}</div>
            </div>
        `;

        panel.querySelector('#myavanaAdminUserSearchButton').addEventListener('click', () => {
            loadUsers(panel.querySelector('#myavanaAdminUserSearch').value.trim());
        });

        panel.querySelectorAll('[data-view-user]').forEach((button) => {
            button.addEventListener('click', () => {
                panel.querySelectorAll('.myavana-admin-user-card').forEach((card) => {
                    card.classList.toggle('is-active', card.dataset.userId === button.dataset.viewUser);
                });
                loadUserDetail(button.dataset.viewUser);
            });
        });
    }

    function renderUserDetail(data) {
        const target = root.querySelector('#myavanaAdminUserDetail');
        if (!target) {
            return;
        }

        const auditRows = (data.audit || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.action_key)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.actor_name)} • ${safeText(item.created_at)}</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.status)}</span>
            </div>
        `).join('');

        target.innerHTML = `
            <div class="myavana-admin-panel" style="margin-top:18px;">
                <div class="myavana-admin-panel__header">
                    <div>
                        <h4 class="myavana-admin-panel__title">${safeText(data.display_name)}</h4>
                        <p class="myavana-admin-panel__desc">${safeText(data.user_email)} • ${safeText((data.roles || []).join(', '))}</p>
                    </div>
                    <span class="myavana-admin-pill">${safeText(data.onboarding_status)}</span>
                </div>
                <div class="myavana-admin-user-metrics">
                    <div class="myavana-admin-user-metric"><strong>${safeText(data.journey_stats?.entries)}</strong><span>Entries</span></div>
                    <div class="myavana-admin-user-metric"><strong>${safeText(data.journey_stats?.goals)}</strong><span>Goals</span></div>
                    <div class="myavana-admin-user-metric"><strong>${safeText(data.journey_stats?.routines)}</strong><span>Routines</span></div>
                </div>
                <div class="myavana-admin-callout" style="margin-top:16px;">
                    Hair Type: ${safeText(data.profile?.hair_type)}<br>
                    Journey Stage: ${safeText(data.profile?.stage)}<br>
                    Last Activity: ${safeText(data.last_activity)}<br>
                    Show Onboarding Flag: ${data.show_onboarding ? 'Enabled' : 'Disabled'}
                </div>
                ${settings.capabilities.manage_support ? `
                <div class="myavana-admin-action-row" style="margin-top:16px;">
                    <button type="button" class="myavana-admin-button myavana-admin-button--ghost" data-support-action="reset-onboarding" data-user-id="${data.id}">Reset onboarding</button>
                    <button type="button" class="myavana-admin-button" data-support-action="send-reset-link" data-user-id="${data.id}">Send reset link</button>
                </div>
                <div class="myavana-admin-action-row" style="margin-top:12px;">
                    <button type="button" class="myavana-admin-button myavana-admin-button--ghost" data-support-action="suspend" data-user-id="${data.id}">${data.suspended ? 'Unsuspend User' : 'Suspend User'}</button>
                    <button type="button" class="myavana-admin-button myavana-admin-button--ghost" data-support-action="impersonate" data-user-id="${data.id}">Login As User</button>
                    <button type="button" class="myavana-admin-button" data-support-action="award-points" data-user-id="${data.id}">Award Points</button>
                </div>
                ` : ''}
                <div style="margin-top:16px;">
                    <h5 class="myavana-admin-panel__title" style="font-size:18px;">Recent audit activity</h5>
                    <div class="myavana-admin-audit-list" style="margin-top:10px;">${auditRows || '<div class="myavana-admin-callout">No matching audit activity yet.</div>'}</div>
                </div>
            </div>
        `;

        target.querySelectorAll('[data-support-action]').forEach((button) => {
            button.addEventListener('click', () => runSupportAction(button.dataset.userId, button.dataset.supportAction, button));
        });
    }

    function renderAi(data) {
        const panel = root.querySelector('#myavanaAiPanel');
        const cards = [
            { label: 'Generated', value: data.summary?.generated, meta: 'Total AI components' },
            { label: 'Saved', value: data.summary?.saved, meta: 'Components saved by users' },
            { label: 'Dismissed', value: data.summary?.dismissed, meta: 'Non-intrusive cards dismissed' },
            { label: 'Applied', value: data.summary?.applied, meta: 'Suggestions acted on' },
        ].map((card) => `
            <article class="myavana-admin-stat-card">
                <div class="myavana-admin-stat-card__label">${safeText(card.label)}</div>
                <div class="myavana-admin-stat-card__value">${safeText(card.value)}</div>
                <div class="myavana-admin-stat-card__meta">${safeText(card.meta)}</div>
            </article>
        `).join('');

        const topPages = (data.top_pages || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.page_context)}</p>
                    <p class="myavana-admin-row__meta">Top AI delivery surface</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.total)}</span>
            </div>
        `).join('');

        const topTypes = (data.top_types || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.component_type)}</p>
                    <p class="myavana-admin-row__meta">Component type mix</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.total)}</span>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">AI Intelligence</h3>
                    <p class="myavana-admin-panel__desc">Phase 2 migration of the AI Lab into the dedicated admin portal.</p>
                </div>
                <div class="myavana-admin-subtle">${safeText(data.range?.date_from)} to ${safeText(data.range?.date_to)}</div>
            </div>
            <div class="myavana-admin-card-grid">${cards}</div>
            <div class="myavana-admin-section-grid" style="margin-top:18px;">
                <div>
                    <div class="myavana-admin-panel__header">
                        <div>
                            <h4 class="myavana-admin-panel__title">Top Pages</h4>
                            <p class="myavana-admin-panel__desc">Where AI suggestions are most frequently generated.</p>
                        </div>
                    </div>
                    <div class="myavana-admin-list">${topPages || '<div class="myavana-admin-callout">No AI page data available yet.</div>'}</div>
                </div>
                <div>
                    <div class="myavana-admin-panel__header">
                        <div>
                            <h4 class="myavana-admin-panel__title">Top Component Types</h4>
                            <p class="myavana-admin-panel__desc">Which AI formats are most active right now.</p>
                        </div>
                    </div>
                    <div class="myavana-admin-list">${topTypes || '<div class="myavana-admin-callout">No component type data available yet.</div>'}</div>
                </div>
            </div>
        `;
    }

    function renderOperations(data) {
        const panel = root.querySelector('#myavanaOperationsPanel');
        const cards = [
            { label: 'Avg Time On Site', value: data.summary?.avg_time_site, meta: 'Seconds' },
            { label: 'Session Depth', value: data.summary?.session_depth, meta: 'Pages per session' },
            { label: 'Returning Rate', value: data.summary?.returning_session_rate, meta: 'Percent' },
            { label: 'Pending Reports', value: data.summary?.pending_reports, meta: 'Moderation queue' },
        ].map((card) => `
            <article class="myavana-admin-stat-card">
                <div class="myavana-admin-stat-card__label">${safeText(card.label)}</div>
                <div class="myavana-admin-stat-card__value">${safeText(card.value)}</div>
                <div class="myavana-admin-stat-card__meta">${safeText(card.meta)}</div>
            </article>
        `).join('');

        const activeUsers = (data.active_users || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.display_name)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.visits_in_range)} visits • ${safeText(item.entries_count)} entries</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.hair_type || 'No hair type')}</span>
            </div>
        `).join('');

        const topPages = (data.top_pages || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.path)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.avg_time)} sec avg time</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.visits)}</span>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Operations</h3>
                    <p class="myavana-admin-panel__desc">Phase 2 migration of operational monitoring out of WordPress admin.</p>
                </div>
            </div>
            <div class="myavana-admin-card-grid">${cards}</div>
            <div class="myavana-admin-section-grid" style="margin-top:18px;">
                <div>
                    <div class="myavana-admin-panel__header">
                        <div>
                            <h4 class="myavana-admin-panel__title">Active Members</h4>
                            <p class="myavana-admin-panel__desc">Top active users in the selected range.</p>
                        </div>
                    </div>
                    <div class="myavana-admin-list">${activeUsers || '<div class="myavana-admin-callout">No active-user data available yet.</div>'}</div>
                </div>
                <div>
                    <div class="myavana-admin-panel__header">
                        <div>
                            <h4 class="myavana-admin-panel__title">Top Pages</h4>
                            <p class="myavana-admin-panel__desc">Most visited pages with average engagement time.</p>
                        </div>
                    </div>
                    <div class="myavana-admin-list">${topPages || '<div class="myavana-admin-callout">No top-page data available yet.</div>'}</div>
                </div>
            </div>
        `;
    }

    function renderJourney(data) {
        const panel = root.querySelector('#myavanaJourneyPanel');
        const cards = [
            { label: 'Analysis Users', value: data.summary?.analysis_users, meta: 'Users with AI history' },
            { label: 'AI Entries', value: data.summary?.ai_entries, meta: 'AI entries in range' },
            { label: 'New Users', value: data.summary?.new_users, meta: 'Registrations in range' },
            { label: 'Unique Visitors', value: data.summary?.unique_visitors, meta: 'Visitor pool' },
        ].map(renderStatCard).join('');

        const funnel = (data.funnel || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.label)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.count)} users</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.drop_off)}% drop</span>
            </div>
        `).join('');

        const aiTrend = (data.ai_entry_trend || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.entry_date)}</p>
                    <p class="myavana-admin-row__meta">AI entry volume</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.total)}</span>
            </div>
        `).join('');

        const activeUsers = (data.active_users || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.display_name)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.entries_count)} entries • ${safeText(item.visits_in_range)} visits</p>
                </div>
                <span class="myavana-admin-pill">Active</span>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Journey Activity</h3>
                    <p class="myavana-admin-panel__desc">Core product monitoring for signup-to-entry-to-analysis behavior.</p>
                </div>
            </div>
            <div class="myavana-admin-card-grid">${cards}</div>
            <div class="myavana-admin-section-grid" style="margin-top:18px;">
                <div>
                    <div class="myavana-admin-panel__header"><div><h4 class="myavana-admin-panel__title">Funnel</h4><p class="myavana-admin-panel__desc">Journey conversion from signup to community participation.</p></div></div>
                    <div class="myavana-admin-list">${funnel || '<div class="myavana-admin-callout">No funnel data yet.</div>'}</div>
                </div>
                <div>
                    <div class="myavana-admin-panel__header"><div><h4 class="myavana-admin-panel__title">AI Entry Trend</h4><p class="myavana-admin-panel__desc">Recent AI entry activity.</p></div></div>
                    <div class="myavana-admin-list">${aiTrend || '<div class="myavana-admin-callout">No AI entry trend available.</div>'}</div>
                </div>
            </div>
            <div class="myavana-admin-panel" style="margin-top:18px;">
                <div class="myavana-admin-panel__header"><div><h4 class="myavana-admin-panel__title">Most Active Members</h4><p class="myavana-admin-panel__desc">Users driving the journey signal right now.</p></div></div>
                <div class="myavana-admin-list">${activeUsers || '<div class="myavana-admin-callout">No active journey users available.</div>'}</div>
            </div>
        `;
    }

    function renderCommunity(data) {
        const panel = root.querySelector('#myavanaCommunityPanel');
        const cards = [
            { label: 'Posts', value: data.summary?.posts_count, meta: 'Community posts in range' },
            { label: 'Video Posts', value: data.summary?.video_count, meta: 'Video-led stories' },
            { label: 'Interactions', value: data.summary?.interactions, meta: 'Likes, comments, shares' },
            { label: 'Pending Reports', value: data.summary?.pending_reports, meta: 'Moderation queue' },
        ].map(renderStatCard).join('');

        const creators = (data.top_creators || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.display_name)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.posts_count)} posts • ${safeText(item.engagement)} engagement</p>
                </div>
                <span class="myavana-admin-pill">Creator</span>
            </div>
        `).join('');

        const reports = (data.reports || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.reason)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.content_type)} #${safeText(item.content_id)} • ${safeText(item.created_at)}</p>
                </div>
                <div class="myavana-admin-action-row">
                    <span class="myavana-admin-pill">${safeText(item.status)}</span>
                    ${item.id ? `<button type="button" class="myavana-admin-button myavana-admin-button--ghost" data-report-action="resolved" data-report-id="${item.id}">Resolve</button>` : ''}
                </div>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Community</h3>
                    <p class="myavana-admin-panel__desc">Participation, creator health, and moderation queue management.</p>
                </div>
                <a href="${endpoint('export')}?type=reports&_wpnonce=${settings.nonce}" class="myavana-admin-button myavana-admin-button--ghost" target="_blank">Export Reports CSV</a>
            </div>
            <div class="myavana-admin-card-grid">${cards}</div>
            <div class="myavana-admin-section-grid" style="margin-top:18px;">
                <div>
                    <div class="myavana-admin-panel__header"><div><h4 class="myavana-admin-panel__title">Top Creators</h4><p class="myavana-admin-panel__desc">Most engaged creators during the selected window.</p></div></div>
                    <div class="myavana-admin-list">${creators || '<div class="myavana-admin-callout">No creator data available.</div>'}</div>
                </div>
                <div>
                    <div class="myavana-admin-panel__header"><div><h4 class="myavana-admin-panel__title">Recent Reports</h4><p class="myavana-admin-panel__desc">Fast moderation actions for current reports.</p></div></div>
                    <div class="myavana-admin-list">${reports || '<div class="myavana-admin-callout">No recent reports.</div>'}</div>
                </div>
            </div>
        `;

        panel.querySelectorAll('[data-report-action]').forEach((button) => {
            button.addEventListener('click', () => resolveCommunityReport(button.dataset.reportId, button.dataset.reportAction, button));
        });
    }

    function renderGamification(data) {
        const panel = root.querySelector('#myavanaGamificationPanel');
        const cards = [
            { label: 'Reward Events', value: data.summary?.reward_events, meta: 'Ledger entries in range' },
            { label: 'Points Awarded', value: data.summary?.points_awarded, meta: 'Total distributed points' },
            { label: 'Rewarded Users', value: data.summary?.rewarded_users, meta: 'Unique rewarded members' },
            { label: 'Challenge Completions', value: data.summary?.challenge_completions, meta: 'Completed challenge events' },
        ].map(renderStatCard).join('');

        const challengeRows = (data.challenge_rows || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.title)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.participants)} participants • ${safeText(item.points)} points</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.completions)} complete</span>
            </div>
        `).join('');

        const rewardInputs = Object.entries(data.reward_field_labels || {}).map(([key, label]) => `
            <label class="myavana-admin-form-field">
                <span>${safeText(label)}</span>
                <input type="number" min="0" name="${key}" value="${safeText(data.reward_settings?.[key] || 0)}">
            </label>
        `).join('');

        const challengeText = JSON.stringify(data.challenge_definitions || [], null, 2);

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Gamification</h3>
                    <p class="myavana-admin-panel__desc">Challenge performance and reward-system controls.</p>
                </div>
            </div>
            <div class="myavana-admin-card-grid">${cards}</div>
            <div class="myavana-admin-section-grid" style="margin-top:18px;">
                <div>
                    <div class="myavana-admin-panel__header"><div><h4 class="myavana-admin-panel__title">Challenge Performance</h4><p class="myavana-admin-panel__desc">Which challenges are actually moving users.</p></div></div>
                    <div class="myavana-admin-list">${challengeRows || '<div class="myavana-admin-callout">No challenge performance yet.</div>'}</div>
                </div>
                <div class="myavana-admin-settings-card">
                    <h4>Reward Value Tuning</h4>
                    <form id="myavanaGamificationRewardsForm" class="myavana-admin-form-grid">${rewardInputs}<button type="submit" class="myavana-admin-button">Save reward settings</button></form>
                </div>
            </div>
            <div class="myavana-admin-settings-card" style="margin-top:18px;">
                <h4>Challenge Definitions</h4>
                <p class="myavana-admin-panel__desc">Edit as JSON for now while the challenge builder UI is still being migrated.</p>
                <form id="myavanaGamificationChallengesForm">
                    <textarea id="myavanaGamificationChallengesJson" class="myavana-admin-textarea">${safeText(challengeText)}</textarea>
                    <button type="submit" class="myavana-admin-button" style="margin-top:14px;">Save challenges</button>
                </form>
            </div>
            <div class="myavana-admin-panel" style="margin-top:24px;">
                <div class="myavana-admin-panel__header">
                    <div>
                        <h4 class="myavana-admin-panel__title">Recent Points Ledger</h4>
                        <p class="myavana-admin-panel__desc">Raw chronological distribution of points.</p>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="myavana-admin-table" style="width:100%; border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr style="border-bottom:1px solid #eee;">
                                <th style="padding:10px;">User</th>
                                <th style="padding:10px;">Event</th>
                                <th style="padding:10px;">Points</th>
                                <th style="padding:10px; text-align:right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${(data.ledger?.items || []).map((item) => `
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:10px;">${safeText(item.display_name)}<br><small class="myavana-admin-subtle">${safeText(item.user_email)}</small></td>
                                    <td style="padding:10px;">${safeText(item.event_key)}</td>
                                    <td style="padding:10px;"><span class="myavana-admin-pill" style="min-width:30px; text-align:center;">+${safeText(item.points)}</span></td>
                                    <td style="padding:10px; text-align:right;" class="myavana-admin-subtle">${safeText(item.created_at)}</td>
                                </tr>
                            `).join('') || '<tr><td colspan="4" class="myavana-admin-callout" style="padding:10px;">No points history recorded yet.</td></tr>'}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        panel.querySelector('#myavanaGamificationRewardsForm').addEventListener('submit', (event) => saveRewardSettings(event, data.reward_field_labels || {}));
        panel.querySelector('#myavanaGamificationChallengesForm').addEventListener('submit', saveChallengeDefinitions);
    }

    function renderControl(data) {
        const panel = root.querySelector('#myavanaControlPanel');
        const diagnostics = Object.entries(data.diagnostics?.tables || {}).map(([key, value]) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(key)}</p>
                    <p class="myavana-admin-row__meta">Backend table availability</p>
                </div>
                <span class="myavana-admin-pill">${value ? 'Ready' : 'Missing'}</span>
            </div>
        `).join('');

        const components = (data.recent_components || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.title)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.page_context)} • ${safeText(item.component_type)} • ${safeText(item.generated_at)}</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.status)}</span>
            </div>
        `).join('');

        const auditPreview = (data.audit_preview?.items || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.action_key)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.actor_name)} • ${safeText(item.created_at)}</p>
                </div>
                <span class="myavana-admin-pill">${safeText(item.status)}</span>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Control Center</h3>
                    <p class="myavana-admin-panel__desc">Advanced diagnostics, AI surface visibility, and system-level signals.</p>
                </div>
            </div>
            <div class="myavana-admin-section-grid">
                <div class="myavana-admin-settings-card">
                    <h4>Diagnostics</h4>
                    <div class="myavana-admin-callout">
                        Portal URL: ${safeText(data.diagnostics?.portal_page_url)}<br>
                        Model: ${safeText(data.diagnostics?.gemini_model)}<br>
                        WordPress: ${safeText(data.diagnostics?.wp_version)}<br>
                        PHP: ${safeText(data.diagnostics?.php_version)}<br>
                        Auth Rewrite Version: ${safeText(data.diagnostics?.auth_rewrite_version)}
                    </div>
                    <div class="myavana-admin-list" style="margin-top:14px;">${diagnostics || '<div class="myavana-admin-callout">No diagnostics available.</div>'}</div>
                </div>
                <div class="myavana-admin-settings-card">
                    <h4>Recent AI Components</h4>
                    <div class="myavana-admin-list">${components || '<div class="myavana-admin-callout">No AI components generated yet.</div>'}</div>
                </div>
            </div>
            <div class="myavana-admin-settings-card" style="margin-top:18px;">
                <h4>Audit Preview</h4>
                <div class="myavana-admin-list">${auditPreview || '<div class="myavana-admin-callout">No audit entries available.</div>'}</div>
            </div>
        `;
    }

    function renderSettings(data) {
        const panel = root.querySelector('#myavanaSettingsPanel');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Settings</h3>
                    <p class="myavana-admin-panel__desc">Foundation settings surface for the new portal and intelligence systems.</p>
                </div>
            </div>
            <div class="myavana-admin-settings-grid">
                <form id="myavanaAdminSettingsForm" class="myavana-admin-settings-card">
                    <h4>API Keys</h4>
                    <input type="password" name="gemini" placeholder="Gemini API key" data-masked="${safeText(data.api_keys?.gemini?.masked)}">
                    <input type="password" name="openai" placeholder="OpenAI API key" data-masked="${safeText(data.api_keys?.openai?.masked)}">
                    <input type="password" name="xai" placeholder="xAI API key" data-masked="${safeText(data.api_keys?.xai?.masked)}">
                    <div class="myavana-admin-callout" style="margin-top:14px;">Leave a field blank to keep the current secret unchanged.</div>
                    <h4 style="margin-top:20px;">Feature Flags</h4>
                    <label class="myavana-admin-toggle"><span>Admin Portal Enabled</span><input type="checkbox" name="admin_portal_enabled" ${data.feature_flags?.admin_portal_enabled ? 'checked' : ''}></label>
                    <label class="myavana-admin-toggle"><span>Site Intelligence Enabled</span><input type="checkbox" name="site_intelligence_enabled" ${data.feature_flags?.site_intelligence_enabled ? 'checked' : ''}></label>
                    <label class="myavana-admin-toggle"><span>AI Intelligence Enabled</span><input type="checkbox" name="ai_intelligence_enabled" ${data.feature_flags?.ai_intelligence_enabled ? 'checked' : ''}></label>
                    
                    <h4 style="margin-top:20px;">Global Announcement</h4>
                    <p class="myavana-admin-panel__desc">Displays a banner at the top of every page. Leave blank to disable.</p>
                    <input type="text" name="global_announcement" placeholder="e.g. New Feature: AI Routines are now live!" value="${safeText(data.global_announcement === '—' ? '' : data.global_announcement)}">

                    <button type="submit" class="myavana-admin-button" style="margin-top:18px;">Save settings</button>
                </form>
            </div>
        `;

        panel.querySelectorAll('input[type="password"]').forEach((input) => {
            if (input.dataset.masked && input.dataset.masked !== '—') {
                input.placeholder = `Current: ${input.dataset.masked}`;
            }
        });

        panel.querySelector('#myavanaAdminSettingsForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const payload = {
                api_keys: {
                    gemini: form.gemini.value.trim(),
                    openai: form.openai.value.trim(),
                    xai: form.xai.value.trim(),
                },
                feature_flags: {
                    admin_portal_enabled: form.admin_portal_enabled.checked,
                    site_intelligence_enabled: form.site_intelligence_enabled.checked,
                    ai_intelligence_enabled: form.ai_intelligence_enabled.checked,
                },
                global_announcement: form.global_announcement.value,
            };

            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Saving…';

            try {
                await request('settings', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                button.textContent = 'Saved';
                setTimeout(() => {
                    button.disabled = false;
                    button.textContent = 'Save settings';
                    loadSettings();
                    loadAudit();
                }, 700);
            } catch (error) {
                button.disabled = false;
                button.textContent = error.message;
                setTimeout(() => {
                    button.textContent = 'Save settings';
                }, 1200);
            }
        });
    }

    function renderAudit(data) {
        const panel = root.querySelector('#myavanaAuditPanel');
        const rows = (data.items || []).map((item) => `
            <div class="myavana-admin-row">
                <div>
                    <p class="myavana-admin-row__title">${safeText(item.action_key)}</p>
                    <p class="myavana-admin-row__meta">${safeText(item.actor_name)} • ${safeText(item.created_at)} • ${safeText(item.target_type || 'system')}</p>
                </div>
                <span class="myavana-admin-audit-status is-${safeText(item.status)}">${safeText(item.status)}</span>
            </div>
        `).join('');

        panel.innerHTML = `
            <div class="myavana-admin-panel__header">
                <div>
                    <h3 class="myavana-admin-panel__title">Audit Log</h3>
                    <p class="myavana-admin-panel__desc">Mutation trail for settings and future admin operations.</p>
                </div>
                <div style="text-align: right;">
                    <span class="myavana-admin-subtle" style="display: block; margin-bottom: 6px;">${safeText(data.pagination?.total)} total events</span>
                    <a href="${endpoint('export')}?type=audit&_wpnonce=${settings.nonce}" class="myavana-admin-button myavana-admin-button--ghost" target="_blank">Export CSV</a>
                </div>
            </div>
            <div class="myavana-admin-audit-list">${rows || '<div class="myavana-admin-callout">No audit entries have been recorded yet.</div>'}</div>
        `;
    }

    async function loadOverview() {
        if (!sectionMap.has('overview')) {
            return;
        }
        const data = await request('overview');
        renderOverview(data);
    }

    async function loadUsers(search = '') {
        if (!sectionMap.has('users')) {
            return;
        }
        const query = search ? `users?search=${encodeURIComponent(search)}` : 'users';
        const data = await request(query);
        renderUsers(data);
    }

    async function loadUserDetail(userId) {
        state.selectedUserId = userId;
        const target = root.querySelector('#myavanaAdminUserDetail');
        if (target) {
            target.innerHTML = '<div class="myavana-admin-callout">Loading user details…</div>';
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        const data = await request(`users/${userId}`);
        renderUserDetail(data);
    }

    async function runSupportAction(userId, action, button) {
        let payload = {};
        if (action === 'award-points') {
            const pointsStr = prompt('Enter points to award:');
            if (!pointsStr) return;
            const points = parseInt(pointsStr, 10);
            if (isNaN(points) || points <= 0) {
                alert('Invalid points amount.');
                return;
            }
            const reason = prompt('Enter reason for award (e.g. Community Bonus):') || 'Admin Bonus';
            payload = { points, reason };
        } else if (action === 'suspend') {
            if (!confirm("Are you sure you want to toggle this user's suspension status?")) return;
        } else if (action === 'impersonate') {
            if (!confirm("This will end your current admin session and log you in as this user to view their dashboard. Continue?")) return;
        }

        const labels = {
            'reset-onboarding': 'Resetting…',
            'send-reset-link': 'Sending…',
            'suspend': 'Updating…',
            'impersonate': 'Swapping…',
            'award-points': 'Awarding…',
        };
        const defaultLabel = button.textContent;
        button.disabled = true;
        button.textContent = labels[action] || 'Working…';

        try {
            const response = await request(`users/${userId}/${action}`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            button.textContent = 'Done';
            
            if (response && response.redirect_url) {
                window.location.href = response.redirect_url;
                return;
            }
            
            await Promise.all([loadUserDetail(userId), loadAudit()]);
        } catch (error) {
            button.textContent = String(error.message).substring(0, 20);
        } finally {
            setTimeout(() => {
                button.disabled = false;
                button.textContent = defaultLabel;
            }, 1200);
        }
    }

    async function loadAi() {
        if (!sectionMap.has('ai')) {
            return;
        }
        const data = await request('ai');
        renderAi(data);
    }

    async function loadOperations() {
        if (!sectionMap.has('operations')) {
            return;
        }
        const data = await request('operations');
        renderOperations(data);
    }

    async function loadSettings() {
        if (!sectionMap.has('settings')) {
            return;
        }
        const data = await request('settings');
        renderSettings(data);
    }

    async function loadAudit() {
        if (!sectionMap.has('audit')) {
            return;
        }
        const data = await request('audit');
        renderAudit(data);
    }

    async function loadJourney() {
        if (!sectionMap.has('journey')) {
            return;
        }
        const data = await request('journey');
        renderJourney(data);
    }

    async function loadCommunity() {
        if (!sectionMap.has('community')) {
            return;
        }
        const data = await request('community');
        renderCommunity(data);
    }

    async function resolveCommunityReport(reportId, action, button) {
        const defaultLabel = button.textContent;
        button.disabled = true;
        button.textContent = 'Updating…';
        try {
            await request(`community/reports/${reportId}/resolve`, {
                method: 'POST',
                body: JSON.stringify({ status: action }),
            });
            await Promise.all([loadCommunity(), loadAudit()]);
            button.textContent = 'Resolved';
        } catch (error) {
            button.textContent = error.message;
        } finally {
            setTimeout(() => {
                button.disabled = false;
                button.textContent = defaultLabel;
            }, 1200);
        }
    }

    async function loadGamification() {
        if (!sectionMap.has('gamification')) {
            return;
        }
        
        try {
            const [data, ledger] = await Promise.all([
                request('gamification'),
                request('gamification/ledger').catch(() => ({ items: [] }))
            ]);
            
            data.ledger = ledger;
            renderGamification(data);
        } catch (error) {
            console.error('Failed to load gamification', error);
        }
    }

    async function saveRewardSettings(event, labels) {
        event.preventDefault();
        const form = event.currentTarget;
        const payload = { reward_settings: {} };
        Object.keys(labels).forEach((key) => {
            payload.reward_settings[key] = Number(form.querySelector(`[name="${key}"]`)?.value || 0);
        });

        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Saving…';
        try {
            await request('gamification/config', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await Promise.all([loadGamification(), loadAudit()]);
            button.textContent = 'Saved';
        } catch (error) {
            button.textContent = error.message;
        } finally {
            setTimeout(() => {
                button.disabled = false;
                button.textContent = 'Save reward settings';
            }, 1200);
        }
    }

    async function saveChallengeDefinitions(event) {
        event.preventDefault();
        const form = event.currentTarget;
        const textarea = form.querySelector('#myavanaGamificationChallengesJson');
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Saving…';

        try {
            const parsed = JSON.parse(textarea.value);
            await request('gamification/config', {
                method: 'POST',
                body: JSON.stringify({ challenges: parsed }),
            });
            await Promise.all([loadGamification(), loadAudit()]);
            button.textContent = 'Saved';
        } catch (error) {
            button.textContent = error.message || 'Invalid JSON';
        } finally {
            setTimeout(() => {
                button.disabled = false;
                button.textContent = 'Save challenges';
            }, 1400);
        }
    }

    async function loadControl() {
        if (!sectionMap.has('control')) {
            return;
        }
        const data = await request('control');
        renderControl(data);
    }

    function renderStatCard(card) {
        return `
            <article class="myavana-admin-stat-card">
                <div class="myavana-admin-stat-card__label">${safeText(card.label)}</div>
                <div class="myavana-admin-stat-card__value">${safeText(card.value)}</div>
                <div class="myavana-admin-stat-card__meta">${safeText(card.meta)}</div>
            </article>
        `;
    }

    async function boot() {
        renderShell();
        syncActiveSection();

        try {
            const tasks = [];
            if (sectionMap.has('overview')) {
                tasks.push(loadOverview());
            }
            if (sectionMap.has('ai')) {
                tasks.push(loadAi());
            }
            if (sectionMap.has('operations')) {
                tasks.push(loadOperations());
            }
            if (sectionMap.has('users')) {
                tasks.push(loadUsers());
            }
            if (sectionMap.has('settings')) {
                tasks.push(loadSettings());
            }
            if (sectionMap.has('journey')) {
                tasks.push(loadJourney());
            }
            if (sectionMap.has('community')) {
                tasks.push(loadCommunity());
            }
            if (sectionMap.has('gamification')) {
                tasks.push(loadGamification());
            }
            if (sectionMap.has('control')) {
                tasks.push(loadControl());
            }
            if (sectionMap.has('audit')) {
                tasks.push(loadAudit());
            }

            await Promise.all(tasks);
        } catch (error) {
            root.innerHTML = `<div class="myavana-admin-portal-empty"><div><h2>Portal failed to load</h2><p>${safeText(error.message)}</p></div></div>`;
        }
    }

    function showToast(message) {
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#222; color:#fff; padding:12px 24px; border-radius:8px; z-index:99999; box-shadow:0 10px 40px rgba(0,0,0,0.2); font-family:monospace, sans-serif; font-size:14px; opacity:0; transition: opacity 0.3s;';
        document.body.appendChild(toast);
        
        // fade in
        setTimeout(() => { toast.style.opacity = '1'; }, 10);
        // fade out
        setTimeout(() => { 
            toast.style.opacity = '0'; 
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    let lastReportCheck = Math.floor(Date.now() / 1000);
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('heartbeat-send', function(e, data) {
            data['myavana_check_reports'] = lastReportCheck;
        });

        jQuery(document).on('heartbeat-tick', function(e, data) {
            if (data['myavana_new_reports']) {
                showToast('New community report requires review.');
                lastReportCheck = Math.floor(Date.now() / 1000);
                
                if (state.activeSection === 'community') {
                    loadCommunity();
                }
            }
        });
    }

    boot();
})();
