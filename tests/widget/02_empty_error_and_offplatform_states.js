const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const { WIDGET: W } = require('./_harness.js');

function boot(url) {
  const dom = new JSDOM('<!doctype html><html><body></body></html>', { url, runScripts:'outside-only', pretendToBeVisual:true });
  const w = dom.window;
  w.fetch = () => Promise.reject(new Error('offline'));
  w.requestAnimationFrame = cb => setTimeout(cb,0);
  global.window = w; global.document = w.document;
  w.eval(fs.readFileSync(W,'utf8'));
  return w;
}

// A. Zero entries -> honest empty state, not a fabricated timeline
let w = boot('https://myhairjourney.ai/#journey');
w.MyavanaWidget.init({ apiBase:'http://x' });
w.MyavanaWidget.registerLocalPlatform({ getJourney: () => Promise.resolve({
  profile:{displayName:'New Member',completionPercentage:10,missingFields:['hairType','porosity','density','length']},
  stats:{currentStreak:0}, entries:[], totalEntries:0, goals:[], badges:[], analytics:{} }) });
setTimeout(() => {
  w.MyavanaWidget.open(); w.MyavanaWidget.switchView('stories');
  const sv = w.document.querySelector('.mya-stories-view');
  ok('empty journey shows a real empty state', /journey starts with one entry/.test(sv.innerHTML));
  ok('empty journey invents no entries', sv.querySelectorAll('.mya-entry').length === 0);
  ok('route pill reads Journey from #journey', w.document.querySelector('.mya-context-text').textContent === 'Journey');

  w.MyavanaWidget.openProfile();
  const pf = w.document.querySelector('.mya-profile-sheet-content');
  ok('new member: all four traits show "+ Add"', pf.querySelectorAll('.mya-pf-cell.empty').length === 4);
  ok('new member: no invented hair type', !/4C|Type 4|Normal Porosity/.test(pf.innerHTML));
  ok('new member: prompted to set a first goal', /Set your first hair goal/.test(pf.innerHTML));
  ok('new member: profile-completion action offered', /Complete my profile \(4 left\)/.test(pf.innerHTML));

  // B. Provider fails -> error state with retry, never fake content
  let w2 = boot('https://myhairjourney.ai/#today');
  w2.MyavanaWidget.init({ apiBase:'http://x' });
  w2.MyavanaWidget.registerLocalPlatform({ getJourney: () => Promise.reject(new Error('500')) });
  setTimeout(() => {
    w2.MyavanaWidget.open(); w2.MyavanaWidget.switchView('stories');
    const sv2 = w2.document.querySelector('.mya-stories-view');
    ok('provider failure shows retry, not fake stories', /Couldn.t reach your journey/.test(sv2.innerHTML));
    ok('route pill reads Today Hub from #today', w2.document.querySelector('.mya-context-text').textContent === 'Today Hub');

    // C. No local platform at all (widget on another surface)
    let w3 = boot('https://shop.myavana.com/products/oil');
    w3.MyavanaWidget.init({ apiBase:'http://x' });
    w3.MyavanaWidget.open();
    w3.MyavanaWidget.switchView('stories');
    ok('off-platform: Stories tab stays hidden',
       w3.document.querySelector('.mya-nav-item[data-view="stories"]').style.display === 'none');
    ok('off-platform: falls back to chat view', w3.document.querySelector('.mya-view[data-view-pane="chat"]').classList.contains('active'));
  }, 40);
}, 40);
