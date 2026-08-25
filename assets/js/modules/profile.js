window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Profile = (function() {
    'use strict';

    let container = null;
    let currentUnit = 'in';

    function init() {
        container = document.querySelector('#view-profile');
        if (!container) return;

        bindEvents();
        refresh();
    }

    async function refresh() {
        if (!container) return;
        try {
            const data = await MyavanaNext.API.get('profile');
            MyavanaNext.Store.set('profile', data);
            render(data);
        } catch (err) {
            console.error('[Profile Refresh Error]', err);
        }
    }

    function render(data) {
        if (!container || !data) return;

        const profile = data.profile || {};
        currentUnit = profile.measurementUnit === 'cm' ? 'cm' : 'in';

        renderHeader(profile, data.dayCount, data.joinDate);
        renderStats(data);
        renderHairId(profile, data.hairIdNote);
        renderMilestones(data.milestones || []);
        renderRecent(data.entries || []);
        renderGoals(data.goalsOverview || { active: [], completed: [] });
        renderRewards(data.stats || {}, data.badges || []);
        renderCommunity(data.community || { stats: {} });
        populateSettings(profile);
        populateEditDrawer(profile);
    }

    /* ---------------------------------------------------------------- */
    function renderHeader(profile, dayCount, joinDate) {
        const avatarEl = container.querySelector('#profile-avatar');
        if (avatarEl && profile.avatarUrl) avatarEl.src = profile.avatarUrl;

        setTxt('#profile-name', profile.displayName || profile.username || 'Member');
        setTxt('#profile-username', `@${profile.username || 'myavana'}`);

        const locationEl = container.querySelector('#profile-location');
        const locationDot = container.querySelector('#profile-location-dot');
        if (locationEl && locationDot) {
            if (profile.location) {
                locationEl.style.display = 'inline';
                locationDot.style.display = 'inline';
                locationEl.textContent = profile.location;
            } else {
                locationEl.style.display = 'none';
                locationDot.style.display = 'none';
            }
        }

        const joinEl = container.querySelector('#profile-join-date');
        if (joinEl) {
            const parsed = joinDate ? new Date(joinDate.replace(' ', 'T')) : null;
            joinEl.textContent = parsed && !isNaN(parsed) ? `Joined ${parsed.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}` : '';
        }
        setTxt('#profile-day-count', `Day ${dayCount || 1}`);

        const tagsWrap = container.querySelector('#profile-hair-tags');
        if (tagsWrap) {
            const tags = [];
            if (profile.hairType) tags.push(`Type ${profile.hairType}`);
            if (profile.porosity) tags.push(`${profile.porosity} Porosity`);
            if (profile.density) tags.push(`${profile.density} Density`);
            tagsWrap.innerHTML = tags.length
                ? tags.map(t => `<span class="myavana-profile-tag">${escapeHtml(t)}</span>`).join('')
                : `<span class="myavana-profile-tag is-muted">Complete your HairID</span>`;
        }
    }

    function renderStats(data) {
        setTxt('#profile-stat-length', data.currentLength != null ? formatLength(data.currentLength) : '—');
        setTxt('#profile-stat-gain', data.lengthGain != null ? `${data.lengthGain > 0 ? '+' : ''}${formatLength(data.lengthGain)}` : '—');
        setTxt('#profile-stat-entries', data.totalEntries || 0);
        setTxt('#profile-stat-health', `${data.healthScore || 0}`);
    }

    function renderHairId(profile, note) {
        const grid = container.querySelector('#profile-hairid-grid');
        if (grid) {
            const fields = [
                ['Type', profile.hairType],
                ['Porosity', profile.porosity],
                ['Density', profile.density],
                ['Length', profile.length],
            ];
            grid.innerHTML = fields.map(([label, value]) => `
                <div class="myavana-profile-hairid-item">
                    <span class="spec-label">${escapeHtml(label)}</span>
                    <strong class="spec-value">${value ? escapeHtml(value) : '—'}</strong>
                </div>
            `).join('');
        }
        setTxt('#profile-hairid-note', note || '');
    }

    function renderMilestones(milestones) {
        const grid = container.querySelector('#profile-milestones-grid');
        if (!grid) return;
        if (!milestones.length) {
            grid.innerHTML = `<div class="myavana-calm-empty"><p>Tag an entry as a milestone or length check with a photo, and it'll show up here.</p></div>`;
            return;
        }
        grid.innerHTML = milestones.map(m => `
            <button type="button" class="myavana-profile-milestone-tile" data-entry-id="${m.id}">
                <img src="${escapeHtml(m.featuredImage)}" alt="${escapeHtml(m.title || '')}" loading="lazy" />
                <span>${escapeHtml(m.date || '')}</span>
            </button>
        `).join('');
        grid.querySelectorAll('[data-entry-id]').forEach(btn => {
            btn.addEventListener('click', () => MyavanaNext.App.navigate('journey'));
        });
    }

    function renderRecent(entries) {
        const list = container.querySelector('#profile-recent-list');
        if (!list) return;
        const recent = entries.slice(0, 3);
        if (!recent.length) {
            list.innerHTML = `<div class="myavana-calm-empty"><p>Your timeline starts with one small update.</p><button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-open-entry>Add your first update</button></div>`;
            list.querySelector('[data-open-entry]')?.addEventListener('click', () => MyavanaNext.SmartEntry?.open());
            return;
        }
        list.innerHTML = recent.map(e => {
            const image = e.featuredImage || (e.photos && e.photos[0]);
            return `
                <article class="myavana-profile-recent-item">
                    ${image ? `<img src="${escapeHtml(image)}" alt="" loading="lazy" />` : `<div class="myavana-profile-recent-placeholder" aria-hidden="true">✦</div>`}
                    <div>
                        <span>${escapeHtml(e.date || '')}</span>
                        <strong>${escapeHtml(e.title || 'Hair update')}</strong>
                        ${e.notes ? `<p>${escapeHtml(e.notes)}</p>` : ''}
                    </div>
                </article>`;
        }).join('');
    }

    function renderGoals(overview) {
        const activeWrap = container.querySelector('#profile-active-goals');
        const achievedWrap = container.querySelector('#profile-achieved-goals');
        const achievedLabel = container.querySelector('#profile-achieved-label');
        const active = overview.active || [];
        const completed = overview.completed || [];

        if (activeWrap) {
            activeWrap.innerHTML = active.length
                ? active.map(goalRowHtml).join('')
                : `<p class="myavana-profile-empty-note">No active goals yet.</p>`;
        }

        if (achievedWrap && achievedLabel) {
            if (completed.length) {
                achievedLabel.style.display = 'block';
                achievedWrap.innerHTML = completed.map(g => `
                    <div class="myavana-profile-achieved-row">
                        <span class="achieved-check">✓</span>
                        <span>${escapeHtml(g.title || g.goal || 'Hair Goal')}</span>
                    </div>
                `).join('');
            } else {
                achievedLabel.style.display = 'none';
                achievedWrap.innerHTML = '';
            }
        }
    }

    function goalRowHtml(g) {
        const prog = Math.max(0, Math.min(100, g.progress || 0));
        return `
            <div class="myavana-profile-goal-row">
                <div class="goal-row-head"><span>${escapeHtml(g.title || g.goal || 'Hair Goal')}</span><strong>${prog}%</strong></div>
                <div class="myavana-profile-goal-bar"><div style="width:${prog}%;"></div></div>
            </div>`;
    }

    function renderRewards(stats, badges) {
        setTxt('#profile-level-title', stats.levelTitle || 'Hair Care Explorer');
        setTxt('#profile-level-points', `${stats.totalPoints || 0} XP`);
        const bar = container.querySelector('#profile-xp-bar');
        if (bar) {
            const next = stats.nextLevelPoints || 100;
            bar.style.width = `${Math.min(100, Math.round(((stats.totalPoints || 0) / next) * 100))}%`;
        }
        const grid = container.querySelector('#profile-badges-grid');
        if (grid) {
            grid.innerHTML = badges.filter(b => b.unlocked).slice(0, 6).map(b => `
                <div class="badge-mini-item" title="${escapeHtml(b.name || 'Badge')}">${b.icon || '🏅'}</div>
            `).join('') || `<span class="myavana-profile-empty-note">Log entries to start earning badges.</span>`;
        }
    }

    function renderCommunity(community) {
        const stats = community.stats || {};
        setTxt('#profile-comm-posts', stats.totalPosts || 0);
        setTxt('#profile-comm-likes', stats.totalLikes || 0);
        setTxt('#profile-comm-followers', stats.followersCount || 0);
    }

    function populateSettings(profile) {
        setVal('#settings-visibility', profile.profileVisibility || 'public');
        setChecked('#settings-activity-status', profile.showActivityStatus !== false);
        setChecked('#settings-email-notifications', profile.emailNotifications !== false);
        setChecked('#settings-comm-notifications', profile.communityNotifications !== false);
        setUnitToggle(profile.measurementUnit === 'cm' ? 'cm' : 'in');
    }

    function setUnitToggle(unit) {
        currentUnit = unit;
        container.querySelectorAll('.unit-toggle-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.unit === unit);
        });
        const hidden = container.querySelector('#settings-measurement-unit');
        if (hidden) hidden.value = unit;
    }

    function formatLength(inches) {
        if (currentUnit === 'cm') {
            return `${(inches * 2.54).toFixed(1)} cm`;
        }
        return `${inches.toFixed(1)} in`;
    }

    function populateEditDrawer(profile) {
        const preview = container.querySelector('#drawer-avatar-preview');
        if (preview && profile.avatarUrl) preview.src = profile.avatarUrl;

        setVal('#drawer-input-name', profile.displayName || '');
        setVal('#drawer-input-bio', profile.bio || '');
        setVal('#drawer-input-location', profile.location || '');
        setVal('#drawer-input-website', profile.website || '');
        setVal('#drawer-input-hair-type', profile.hairType || '');
        setVal('#drawer-input-porosity', profile.porosity || '');
        setVal('#drawer-input-density', profile.density || '');
        setVal('#drawer-input-length', profile.length || '');
        setVal('#drawer-input-health', profile.hairHealthRating || 8);

        const countEl = container.querySelector('#drawer-bio-char-count');
        if (countEl) countEl.textContent = `${(profile.bio || '').length} / 200`;

        const userConcerns = profile.concerns || [];
        container.querySelectorAll('input[name="concerns[]"]').forEach(cb => {
            cb.checked = userConcerns.includes(cb.value);
        });
    }

    /* ---------------------------------------------------------------- */
    function bindEvents() {
        container.querySelector('#profile-view-timeline')?.addEventListener('click', () => MyavanaNext.App.navigate('journey'));
        container.querySelector('#profile-view-community')?.addEventListener('click', () => MyavanaNext.App.navigate('community'));

        container.querySelector('#btn-copy-profile-link')?.addEventListener('click', () => {
            const url = window.location.href.split('#')[0];
            if (navigator.clipboard?.writeText) {
                navigator.clipboard.writeText(url).then(() => MyavanaNext.API.showToast('Profile link copied to clipboard!', 'success'));
            } else {
                MyavanaNext.API.showToast('Profile link: ' + url, 'info');
            }
        });

        container.querySelectorAll('.unit-toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => setUnitToggle(btn.dataset.unit));
        });

        const drawer = container.querySelector('#profile-edit-drawer');
        const openDrawer = () => { drawer?.classList.add('active'); drawer?.setAttribute('aria-hidden', 'false'); };
        const closeDrawer = () => { drawer?.classList.remove('active'); drawer?.setAttribute('aria-hidden', 'true'); };

        container.querySelector('#btn-open-edit-drawer')?.addEventListener('click', openDrawer);
        container.querySelector('#btn-open-avatar-picker')?.addEventListener('click', openDrawer);
        container.querySelector('#btn-close-edit-drawer')?.addEventListener('click', closeDrawer);
        container.querySelector('#profile-drawer-overlay')?.addEventListener('click', closeDrawer);
        container.querySelector('#btn-cancel-drawer')?.addEventListener('click', closeDrawer);
        container.querySelectorAll('.btn-card-edit[data-edit]').forEach(btn => btn.addEventListener('click', openDrawer));
        container.querySelector('.btn-card-edit[data-action="manage-goals"]')?.addEventListener('click', () => MyavanaNext.App.navigate('routine'));

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && drawer?.classList.contains('active')) closeDrawer();
        });

        const bioInput = container.querySelector('#drawer-input-bio');
        const bioCounter = container.querySelector('#drawer-bio-char-count');
        bioInput?.addEventListener('input', () => {
            if (bioCounter) bioCounter.textContent = `${bioInput.value.length} / 200`;
        });

        const fileInput = container.querySelector('#drawer-avatar-file-input');
        container.querySelector('#btn-choose-avatar')?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', async () => {
            const file = fileInput.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = async (event) => {
                const base64Data = event.target.result;
                const preview = container.querySelector('#drawer-avatar-preview');
                if (preview) preview.src = base64Data;
                try {
                    const res = await MyavanaNext.API.post('profile/avatar', { avatarData: base64Data });
                    if (res?.avatarUrl) {
                        const heroAvatar = container.querySelector('#profile-avatar');
                        if (heroAvatar) heroAvatar.src = res.avatarUrl;
                        MyavanaNext.API.showToast('Profile photo updated!', 'success');
                    }
                } catch (err) {
                    console.error('[Avatar Upload Error]', err);
                    MyavanaNext.API.showToast('Failed to upload photo', 'error');
                }
            };
            reader.readAsDataURL(file);
        });

        const drawerForm = container.querySelector('#drawer-profile-form');
        drawerForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const concerns = [];
            container.querySelectorAll('input[name="concerns[]"]:checked').forEach(cb => concerns.push(cb.value));

            const payload = {
                displayName: container.querySelector('#drawer-input-name')?.value,
                bio: container.querySelector('#drawer-input-bio')?.value,
                location: container.querySelector('#drawer-input-location')?.value,
                website: container.querySelector('#drawer-input-website')?.value,
                hairType: container.querySelector('#drawer-input-hair-type')?.value,
                porosity: container.querySelector('#drawer-input-porosity')?.value,
                density: container.querySelector('#drawer-input-density')?.value,
                length: container.querySelector('#drawer-input-length')?.value,
                hairHealthRating: container.querySelector('#drawer-input-health')?.value,
                concerns,
            };

            const saveBtn = container.querySelector('#btn-save-drawer');
            try {
                if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving...'; }
                await MyavanaNext.API.post('profile', payload);
                MyavanaNext.API.showToast('Profile updated successfully!', 'success');
                closeDrawer();
                refresh();
            } catch (err) {
                console.error('[Save Profile Error]', err);
                MyavanaNext.API.showToast('Error saving profile changes', 'error');
            } finally {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Changes'; }
            }
        });

        const settingsForm = container.querySelector('#profile-settings-form');
        settingsForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                profileVisibility: container.querySelector('#settings-visibility')?.value,
                showActivityStatus: container.querySelector('#settings-activity-status')?.checked,
                emailNotifications: container.querySelector('#settings-email-notifications')?.checked,
                communityNotifications: container.querySelector('#settings-comm-notifications')?.checked,
                measurementUnit: container.querySelector('#settings-measurement-unit')?.value || 'in',
            };
            try {
                await MyavanaNext.API.post('profile', payload);
                MyavanaNext.API.showToast('Preferences saved!', 'success');
                refresh();
            } catch (err) {
                console.error('[Save Settings Error]', err);
                MyavanaNext.API.showToast('Failed to save preferences', 'error');
            }
        });

        const exportBtn = container.querySelector('#btn-export-user-data');
        exportBtn?.addEventListener('click', async () => {
            try {
                exportBtn.disabled = true;
                const data = await MyavanaNext.API.get('profile/export');
                const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `myavana-hair-journey-${new Date().toISOString().slice(0, 10)}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                MyavanaNext.API.showToast('Hair Journey archive exported!', 'success');
            } catch (err) {
                console.error('[Export Data Error]', err);
                MyavanaNext.API.showToast('Failed to export data archive', 'error');
            } finally {
                exportBtn.disabled = false;
            }
        });
    }

    /* ---------------------------------------------------------------- */
    function setTxt(selector, text) { const el = container.querySelector(selector); if (el) el.textContent = text; }
    function setVal(selector, val) { const el = container.querySelector(selector); if (el) el.value = val; }
    function setChecked(selector, checked) { const el = container.querySelector(selector); if (el) el.checked = !!checked; }
    function escapeHtml(str) { if (!str) return ''; const d = document.createElement('div'); d.textContent = String(str); return d.innerHTML; }

    return { init, refresh };
})();
