const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const { WIDGET: W } = require('./_harness.js');

const dom = new JSDOM('<!doctype html><html><body></body></html>', {
  url: 'https://myhairjourney.ai/#routine', runScripts: 'outside-only', pretendToBeVisual: true
});
const { window } = dom;
window.fetch = () => Promise.reject(new Error('offline'));
window.requestAnimationFrame = cb => setTimeout(cb, 0);
window.matchMedia = () => ({ matches: false, addListener(){}, removeListener(){} });
global.window = window; global.document = window.document;

window.eval(fs.readFileSync(W, 'utf8'));
const MW = window.MyavanaWidget;
MW.init({ apiBase: 'http://localhost:8080', position: 'bottom-left' });

const q = s => window.document.querySelector(s);

// Page awareness from the hash alone
ok('context pill reads Routine from #routine', q('.mya-context-text').textContent === 'Routine');

// Stories/Profile hidden with no local platform
const navHidden = q('.mya-nav-item[data-view="stories"]').style.display === 'none';
ok('Stories tab hidden without a local platform', navHidden);

// Register a local platform with REAL WordPress-shaped data
const journey = {
  profile: { displayName: 'Test Member', hairType: '4C', porosity: 'High', density: '',
             length: '', completionPercentage: 62, missingFields: ['density','length'],
             concerns: ['Breakage','Dryness'], hairJourneyStage: 'Restoring', avatarUrl: '' },
  stats: { currentStreak: 4, longestStreak: 11, totalPoints: 240, level: 3,
           levelTitle: 'Hair Care Adept', nextLevelPoints: 400, totalCheckins: 9 },
  entries: [
    { id: 11, title: 'Wash day + steam', date: '2026-09-01 09:12:00', entryType: 'wash_day',
      mood: 'Hydrated', moistureLevel: 4, scalpState: 'Calm', notes: 'Clarified then deep conditioned.',
      photos: ['https://x/a.jpg','https://x/b.jpg'], featuredImage: 'https://x/a.jpg',
      productsUsed: ['Hydrating mask'], hairLength: null },
    { id: 9, title: 'Length check', date: '2026-08-14 18:00:00', entryType: 'length_check',
      moistureLevel: 3, hairLength: 12.5, hairLengthPoint: 'crown', photos: [], notes: '' }
  ],
  totalEntries: 2, dayCount: 74, currentLength: 12.5, lengthGain: 1.4,
  goals: [{ id:'g1', title:'Retain 14 inches', category:'Length Retention', progress: 46, target_date:'2026-12-01', status:'active' }],
  goalsOverview: { active: [{ id:'g1', title:'Retain 14 inches', category:'Length Retention', progress:46, target_date:'2026-12-01' }], completed: [] },
  badges: [{ id:'b1', name:'First Step', icon:'', description:'d', unlocked:true },
           { id:'b2', name:'Habit Builder', icon:'', description:'d', unlocked:false }],
  analytics: { totalEntries: 2, topMood: 'Hydrated', avgHealthScore: 3.5 },
  hairIdNote: 'Type 4C hair absorbs moisture quickly but can lose it just as fast.'
};

MW.registerLocalPlatform({ name:'WP', getJourney: () => Promise.resolve(journey),
  openEntryComposer(){}, navigate(){} });

setTimeout(() => {
  ok('Stories tab revealed once platform registers',
     q('.mya-nav-item[data-view="stories"]').style.display !== 'none');

  MW.open(); MW.switchView('stories');

  const sv = q('.mya-stories-view');
  const bandNums = [...sv.querySelectorAll('.mya-band-num')].map(n => n.textContent);
  ok('band shows real totals (2 entries, 4 streak, +1.4in): ' + JSON.stringify(bandNums),
     bandNums[0] === '2' && bandNums[1] === '4' && bandNums[2].startsWith('+1.4'));

  ok('reel shows only the one entry that has a photo',
     sv.querySelectorAll('.mya-reel-item .mya-reel-img').length === 1);
  ok('multi-photo badge rendered', /2 photos/.test(sv.innerHTML));
  ok('months grouped', sv.querySelectorAll('.mya-month').length === 2);
  ok('length chip uses real reading', /12\.5&quot;|12\.5"/.test(sv.innerHTML) && /crown/.test(sv.innerHTML));
  ok('no invented imagery', !/unsplash/.test(sv.innerHTML));

  // Entry detail
  sv.querySelector('.mya-entry').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  const d = q('.mya-detail');
  ok('entry detail overlay opens', !!d);
  ok('detail shows both thumbnails', d.querySelectorAll('.mya-detail-thumbs img').length === 2);
  ok('detail lists products', /Hydrating mask/.test(d.innerHTML));
  d.querySelector('.mya-detail-x').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
  ok('entry detail closes', !q('.mya-detail'));

  // Profile
  MW.openProfile();
  const pf = q('.mya-profile-sheet-content');
  ok('profile shows real name', /Test Member/.test(pf.innerHTML));
  ok('completion ring percentage shown', /62% done/.test(pf.innerHTML));
  ok('filled traits render real values', /4C/.test(pf.innerHTML) && /High/.test(pf.innerHTML));
  ok('empty traits invite input, never invent',
     pf.querySelectorAll('.mya-pf-cell.empty').length === 1 && !/Medium Density/.test(pf.innerHTML));
  ok('goal progress is the recorded 46%', /46%/.test(pf.innerHTML));
  ok('level progress shown', /Level 3/.test(pf.innerHTML) && /240\/400/.test(pf.innerHTML));
  ok('only earned badges shown as earned',
     pf.querySelectorAll('.mya-badge:not(.locked)').length === 1 &&
     /1 to unlock/.test(pf.innerHTML));
  ok('hairIdNote surfaced', /absorbs moisture quickly/.test(pf.innerHTML));

  // Route change updates starters
  MW.switchView('chat');
  MW.setContext({ route: 'community' });
  ok('context pill follows route change', q('.mya-context-text').textContent === 'Community');
  ok('starters are community-specific', /trending for my texture/.test(q('.mya-stream').innerHTML));
}, 60);
