const { JSDOM, ok } = require('./_harness.js');
const fs = require('fs');
const { WIDGET: W } = require('./_harness.js');

const dom = new JSDOM('<!doctype html><html><body></body></html>',
  { url:'https://myhairjourney.ai/#today', runScripts:'outside-only', pretendToBeVisual:true });
const w = dom.window;
w.requestAnimationFrame = cb => setTimeout(cb,0);
w.fetch = () => Promise.reject(new Error('offline'));
global.window = w; global.document = w.document;
w.eval(fs.readFileSync(W,'utf8'));

const MW = w.MyavanaWidget;
MW.init({ apiBase:'http://x' });
const q = s => w.document.querySelector(s);
const css = w.document.getElementById('myavana-widget-styles').textContent;

// --- Search input border ---
ok('search field has a visibly stronger border than a card',
   /\.mya-history-search\{[^}]*border:1\.5px solid rgba\(34, 35, 35, 0\.22\)/.test(css));
ok('search field has an inset top highlight so its upper edge reads',
   /\.mya-history-search\{[\s\S]*?box-shadow:inset 0 1px 0 rgba\(255,255,255,0\.9\)/.test(css));
ok('composer wrapper gets the same crisp edge',
   /\.mya-input-wrapper\{[\s\S]*?box-shadow:inset 0 1px 0 rgba\(255,255,255,0\.9\)/.test(css));
ok('New Conversation shadow no longer bleeds onto the field below',
   /\.mya-new-chat-btn\{[\s\S]*?box-shadow:0 2px 6px/.test(css));
ok('toolbar gives the search field clearance', /\.mya-history-toolbar\{[^}]*margin-bottom:14px/.test(css));
ok('search wrap stacks above the button shadow', /\.mya-search-wrap\{[^}]*z-index:1/.test(css));

// --- Composer autosize ---
const ta = q('.mya-textarea');
ok('composer starts at one line', ta.style.height === '' || parseInt(w.getComputedStyle(ta).height) <= 30);
ok('composer max-height capped', /max-height:132px/.test(css));
ok('no scrollbar until the cap is hit', /\.mya-textarea\{[^}]*overflow-y:hidden/.test(css));

// jsdom has no layout, so scrollHeight is 0 — assert the mechanism instead.
const src = fs.readFileSync(W,'utf8');
ok('autosize collapses to 0 before measuring (the shrink bug)',
   /ta\.style\.height = '0px';[\s\S]{0,120}ta\.scrollHeight/.test(src));
ok('autosize clamps between min and max',
   /Math\.max\(COMPOSER_MIN_H, Math\.min\(needed, COMPOSER_MAX_H\)\)/.test(src));
ok('blur with an empty draft collapses it', /addEventListener\('blur'[\s\S]{0,120}autosizeComposer/.test(src));
ok('sending resets the height', /textarea\.value = '';\s*\n\s*autosizeComposer\(\);/.test(src));
ok('dictation no longer sets height by hand', !/style\.height = Math\.min\(state\.els\.textarea\.scrollHeight/.test(src));

// --- View area / launcher morph ---
ok('panel drops to the launcher corner', /\.mya-panel\{[^}]*bottom:20px/.test(css));
ok('panel is taller than before', /height:min\(840px, calc\(100vh - 40px\)\)/.test(css));
ok('panel is wider than before', /\.mya-panel\{[^}]*width:520px/.test(css));
ok('chrome trimmed so the stream gets the space', /\.mya-header\{[^}]*padding:9px 14px/.test(css) && /\.mya-nav-item\{[^}]*padding:6px 4px/.test(css));
ok('expanded mode is near-fullscreen', /is-expanded\{width:min\(1040px[^}]*height:calc\(100vh - 28px\)/.test(css));
ok('panel grows out of the launcher corner', /transform-origin:bottom left/.test(css));
ok('right-positioned panel mirrors its origin', /is-right\{transform-origin:bottom right/.test(css));

MW.open();
ok('launcher morphs away when the panel opens', q('.mya-launcher').classList.contains('is-morphed'));
ok('launcher stops taking clicks while morphed', /is-morphed\{[^}]*pointer-events:none/.test(css));
MW.close();
ok('launcher returns on close', !q('.mya-launcher').classList.contains('is-morphed'));
ok('launcher is not display:none, so it animates back', !/is-morphed\{[^}]*display:none/.test(css));
