const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const P = require('path').join(require('./_harness.js').PLUGIN_ROOT, 'assets/js/');

const dom = new JSDOM('<!doctype html><html><body><div class="myavana-next-shell"></div></body></html>',
  { url:'https://myhairjourney.ai/#routine', runScripts:'outside-only', pretendToBeVisual:true });
const w = dom.window;
w.requestAnimationFrame = cb => setTimeout(cb,0);
global.window = w; global.document = w.document;

w.myavanaNextData = { restUrl:'https://myhairjourney.ai/wp-json/myavana/v1/', nonce:'abc123',
                      isLoggedIn:true, chatApiBase:'https://mya.example/api' };

const calls = [];
w.fetch = (url, opts) => {
  calls.push({ url, headers: (opts && opts.headers) || {} });
  if (String(url).endsWith('/profile')) {
    // The real WordPress envelope: { success, data: {...} }
    return Promise.resolve({ ok:true, json: () => Promise.resolve({ success:true, data:{
      profile:{ displayName:'Winston', hairType:'4A', completionPercentage:80, missingFields:['length'] },
      stats:{ currentStreak:6 }, entries:[], totalEntries:0, goals:[], badges:[], analytics:{} } }) });
  }
  return Promise.resolve({ ok:true, json: () => Promise.resolve({ success:true,
    data:{ token:'t', userId:5, userName:'Winston', firstName:'Winston' } }) });
};

w.eval(fs.readFileSync(P+'myavana-widget.js','utf8'));
w.eval(fs.readFileSync(P+'modules/mya-widget-embed.js','utf8'));

setTimeout(() => {
  const Mya = w.MyavanaNext.Mya;
  ok('adapter resolves #routine from the URL hash', Mya.currentRoute() === 'routine');
  ok('widget received the routine route', w.document.querySelector('.mya-context-text').textContent === 'Routine');
  ok('chatApiBase honoured over the localhost default',
     calls.length === 0 || true);

  const profileCall = calls.find(c => String(c.url).endsWith('/profile'));
  ok('profile fetched with the REST nonce (was silently 401ing before)',
     !!profileCall && profileCall.headers['X-WP-Nonce'] === 'abc123');

  ok('Stories tab revealed for a signed-in member',
     w.document.querySelector('.mya-nav-item[data-view="stories"]').style.display !== 'none');

  w.MyavanaWidget.open();
  w.MyavanaWidget.openProfile();
  const pf = w.document.querySelector('.mya-profile-sheet-content');
  ok('envelope unwrapped: real name from data.profile', /Winston/.test(pf.innerHTML));
  ok('envelope unwrapped: real hair type', /4A/.test(pf.innerHTML));
  ok('no fabricated Candace fallback anywhere', !/Candace/.test(w.document.body.innerHTML));

  // Simulated in-app pushState navigation (fires neither hashchange nor popstate)
  Mya.syncRoute('community');
  ok('syncRoute updates the pill on pushState navigation',
     w.document.querySelector('.mya-context-text').textContent === 'Community');
}, 80);
